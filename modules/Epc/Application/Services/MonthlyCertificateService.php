<?php

declare(strict_types=1);

namespace Modules\Epc\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Epc\Domain\Models\EpcCipCapitalization;
use Modules\Epc\Domain\Models\EpcProgressCertificate;
use Modules\Epc\Domain\Models\EpcProject;
use Modules\Epc\Domain\Models\EpcWbsNode;
use Modules\Epc\Ledger\EpcAccounts;
use Modules\Epc\Ledger\EpcTransactionTypes;
use Modules\Shared\Domain\Math\WeightedTree;
use Modules\Shared\Domain\Math\WeightedTreeNode;

class MonthlyCertificateService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * Compute bottom-up WBS physical progress using PR2 WeightedTree verification.
     */
    public function verifyProjectWbsProgress(EpcProject $project): float
    {
        $nodes = EpcWbsNode::where('project_id', $project->id)->get();
        if ($nodes->isEmpty()) {
            return 0.0;
        }

        $totalWeight = (float) $nodes->sum('weight_percentage');
        if (abs($totalWeight - 100.0) < 0.01) {
            $leaves = [];
            foreach ($nodes as $node) {
                $weightBps = (int) round(((float) $node->weight_percentage) * 100);
                $progressBps = (int) round(((float) $node->completion_percentage) * 100);
                $leaves[] = new WeightedTreeNode((string) $node->wbs_code, $weightBps, $progressBps);
            }

            $root = new WeightedTreeNode('root', 10_000, null, $leaves);
            $treeResult = WeightedTree::computeProgress($root);

            return round($treeResult->progressBps / 100, 2);
        }

        // Direct weighted average fallback if weights do not sum to 100%
        $weightedProgress = 0.0;
        foreach ($nodes as $node) {
            $weightedProgress += (((float) $node->weight_percentage) * ((float) $node->completion_percentage)) / 100.0;
        }

        return round($weightedProgress, 2);
    }

    /**
     * Issue Monthly Certificate (MC) with 5% retention and CIP ledger recognition.
     */
    public function issueCertifiedProgress(
        EpcProject $project,
        float $claimedIncrementalPct,
        string $consultantName
    ): EpcProgressCertificate {
        return DB::transaction(function () use ($project, $claimedIncrementalPct, $consultantName) {
            $locked = EpcProject::where('id', $project->id)->lockForUpdate()->firstOrFail();

            $verifiedProgress = $this->verifyProjectWbsProgress($locked);
            $currentActual = (float) $locked->actual_physical_progress_pct;
            $newCumulative = $currentActual + $claimedIncrementalPct;

            // Ensure claimed progress does not exceed verified WBS physical progress
            if ($newCumulative > ($verifiedProgress + 0.01)) {
                throw new InvalidArgumentException("Claimed progress does not match verified WBS completion (claimed {$newCumulative}%, verified {$verifiedProgress}%).");
            }

            $grossClaim = (int) round(($locked->total_rab_budget_idr * $claimedIncrementalPct) / 100);
            $retention = (int) round($grossClaim * 0.05); // 5% retention hold
            $netPayable = $grossClaim - $retention;

            $certNumber = 'MC-'.strtoupper(Str::random(6));

            $certificate = EpcProgressCertificate::create([
                'project_id' => $locked->id,
                'certificate_number' => $certNumber,
                'period_month' => (int) date('n'),
                'period_year' => (int) date('Y'),
                'certified_cumulative_progress_pct' => min(100.0, $newCumulative),
                'certified_incremental_progress_pct' => $claimedIncrementalPct,
                'gross_claim_amount_idr' => $grossClaim,
                'retention_deduction_idr' => $retention,
                'net_payable_amount_idr' => $netPayable,
                'supervising_consultant_name' => $consultantName,
                'certified_at' => now(),
                'status' => 'approved',
            ]);

            $locked->update([
                'actual_physical_progress_pct' => min(100.0, $newCumulative),
                'accumulated_cip_cost_idr' => $locked->accumulated_cip_cost_idr + $grossClaim,
                'status' => $newCumulative >= 100.0 ? 'bast_final_issued' : 'in_progress',
            ]);

            // Post ledger entries: Debit CIP, Credit Contractor Retention & Payable
            EpcAccounts::cip((string) $locked->id);
            $lines = Posting::lines()->debit('asset:epc:construction_in_progress:IDR', $grossClaim);
            if ($retention > 0) {
                $lines->credit('ap:epc:contractor_retention:IDR', $retention);
            }
            if ($netPayable > 0) {
                $lines->credit('ap:epc:contractor_payable:IDR', $netPayable);
            }

            $this->ledgerService->post(new PostingDTO(
                type: EpcTransactionTypes::EPC_PROGRESS_CERTIFICATE->value,
                description: "EPC MC {$certificate->certificate_number} for {$locked->project_code}",
                idempotencyKey: "EPC-MC-{$certificate->certificate_number}",
                entries: $lines->toEntries('IDR'),
                referenceType: 'EPC_CERTIFICATE',
                referenceId: (string) $certificate->id,
            ));

            return $certificate;
        });
    }

    /**
     * Issue BAST Final (requires 100% completed physical progress).
     */
    public function issueBastFinal(EpcProject $project): EpcCipCapitalization
    {
        return DB::transaction(function () use ($project) {
            $locked = EpcProject::where('id', $project->id)->lockForUpdate()->firstOrFail();

            if ((float) $locked->actual_physical_progress_pct < 100.0) {
                throw new InvalidArgumentException("BAST Final requires 100% physical progress (currently {$locked->actual_physical_progress_pct}%).");
            }

            $bastNumber = 'BAST-FINAL-'.strtoupper(Str::random(6));

            $capitalization = EpcCipCapitalization::create([
                'project_id' => $locked->id,
                'bast_number' => $bastNumber,
                'bast_type' => 'BAST_FINAL',
                'total_cip_cost_idr' => $locked->accumulated_cip_cost_idr,
                'target_asset_category' => 'BUILDING',
                'created_asset_id' => 'AST-BLD-'.strtoupper(Str::random(6)),
                'capitalized_at' => now(),
                'notes' => 'BAST Final Acceptance and completion handover.',
            ]);

            $locked->update([
                'status' => 'bast_final_issued',
            ]);

            $capitalization->status = 'HANDED_OVER';

            return $capitalization;
        });
    }

    /**
     * Capitalize CIP to Fixed Asset (requires BAST Final approved).
     */
    public function capitalizeProjectToAsset(EpcProject $project): EpcCipCapitalization
    {
        return DB::transaction(function () use ($project) {
            $locked = EpcProject::where('id', $project->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'bast_final_issued' && (float) $locked->actual_physical_progress_pct < 100.0) {
                throw new InvalidArgumentException("Cannot capitalize CIP before BAST Final is approved for project {$locked->project_code}.");
            }

            $cipCost = $locked->accumulated_cip_cost_idr;
            if ($cipCost <= 0) {
                throw new InvalidArgumentException("Project {$locked->project_code} has no accumulated CIP cost to capitalize.");
            }

            $bastNumber = 'BAST-CAP-'.strtoupper(Str::random(6));
            $assetId = 'AST-BLD-'.strtoupper(Str::random(6));

            $capitalization = EpcCipCapitalization::create([
                'project_id' => $locked->id,
                'bast_number' => $bastNumber,
                'bast_type' => 'BAST_FINAL',
                'total_cip_cost_idr' => $cipCost,
                'target_asset_category' => 'BUILDING',
                'created_asset_id' => $assetId,
                'capitalized_at' => now(),
                'notes' => 'Reclassification from CIP into Fixed Asset register.',
            ]);

            $locked->update([
                'accumulated_cip_cost_idr' => 0,
                'capitalized_asset_value_idr' => $locked->capitalized_asset_value_idr + $cipCost,
                'status' => 'capitalized',
            ]);

            // Post ledger entry: Debit Fixed Assets, Credit Construction in Progress (CIP)
            $this->ledgerService->post(new PostingDTO(
                type: EpcTransactionTypes::EPC_CIP_CAPITALIZATION->value,
                description: "EPC CIP Capitalization BAST {$capitalization->bast_number} for {$locked->project_code}",
                idempotencyKey: "EPC-CAP-{$capitalization->bast_number}",
                entries: Posting::lines()
                    ->debit('asset:fixed:capitalized_buildings:IDR', $cipCost)
                    ->credit('asset:epc:construction_in_progress:IDR', $cipCost)
                    ->toEntries('IDR'),
                referenceType: 'EPC_CAPITALIZATION',
                referenceId: (string) $capitalization->id,
            ));

            return $capitalization;
        });
    }

    /**
     * Release maintenance warranty retention to contractor payable.
     */
    public function releaseRetention(EpcProject $project, int $retentionAmountIdr, ?string $idempotencyKey = null): array
    {
        return DB::transaction(function () use ($project, $retentionAmountIdr, $idempotencyKey) {
            $key = $idempotencyKey ?? "EPC-RET-REL-{$project->id}-{$retentionAmountIdr}";
            $this->ledgerService->post(new PostingDTO(
                type: EpcTransactionTypes::EPC_RETENTION_RELEASE->value,
                description: "Release maintenance retention for {$project->project_code}",
                idempotencyKey: $key,
                entries: Posting::lines()
                    ->debit('ap:epc:contractor_retention:IDR', $retentionAmountIdr)
                    ->credit('ap:epc:contractor_payable:IDR', $retentionAmountIdr)
                    ->toEntries('IDR'),
                referenceType: 'EPC_PROJECT',
                referenceId: (string) $project->id,
            ));

            return [
                'status' => 'RELEASED',
                'amount_idr' => $retentionAmountIdr,
            ];
        });
    }
}
