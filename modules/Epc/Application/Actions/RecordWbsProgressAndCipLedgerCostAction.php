<?php

declare(strict_types=1);

namespace Modules\Epc\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Epc\Domain\Models\EpcProgressCertificate;
use Modules\Epc\Domain\Models\EpcProject;
use Modules\Epc\Domain\Models\EpcWbsNode;
use Modules\Epc\Ledger\EpcTransactionTypes;

class RecordWbsProgressAndCipLedgerCostAction
{
    public function __construct(
        private readonly LedgerService $ledgerService
    ) {}

    /**
     * @param array{
     *     project_id: string,
     *     wbs_code?: string,
     *     wbs_completion_pct?: float|int,
     *     certified_incremental_progress_pct: float|int,
     *     certified_cumulative_progress_pct?: float|int,
     *     certificate_number?: string,
     *     period_month?: int,
     *     period_year?: int,
     *     supervising_consultant_name?: string
     * } $data
     * @return array{
     *     certificate: EpcProgressCertificate,
     *     project: EpcProject,
     *     gross_claim_idr: int,
     *     retention_idr: int,
     *     net_payable_idr: int
     * }
     */
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $project = EpcProject::where('id', $data['project_id'])->lockForUpdate()->firstOrFail();

            $incrementalPct = (float) ($data['certified_incremental_progress_pct'] ?? 0);
            if ($incrementalPct <= 0) {
                throw new InvalidArgumentException('Certified incremental progress percentage must be greater than zero.');
            }

            // Update specific WBS node if provided
            if (! empty($data['wbs_code'])) {
                $wbs = EpcWbsNode::where('project_id', $project->id)
                    ->where('wbs_code', $data['wbs_code'])
                    ->first();
                if ($wbs && isset($data['wbs_completion_pct'])) {
                    $completion = min(100.0, (float) $data['wbs_completion_pct']);
                    $wbs->update([
                        'completion_percentage' => $completion,
                        'status' => $completion >= 100.0 ? 'completed' : 'ongoing',
                    ]);
                }
            }

            $currentCumulative = (float) $project->actual_physical_progress_pct;
            $newCumulative = $data['certified_cumulative_progress_pct'] ?? ($currentCumulative + $incrementalPct);
            $newCumulative = min(100.0, (float) $newCumulative);

            $grossClaim = (int) round(($project->total_rab_budget_idr * $incrementalPct) / 100);
            $retentionDeduction = (int) round($grossClaim * 0.05); // 5% retention hold
            $netPayable = $grossClaim - $retentionDeduction;

            $certNumber = $data['certificate_number'] ?? 'MC-'.strtoupper(Str::random(6));

            $certificate = EpcProgressCertificate::create([
                'project_id' => $project->id,
                'certificate_number' => $certNumber,
                'period_month' => (int) ($data['period_month'] ?? date('n')),
                'period_year' => (int) ($data['period_year'] ?? date('Y')),
                'certified_cumulative_progress_pct' => $newCumulative,
                'certified_incremental_progress_pct' => $incrementalPct,
                'gross_claim_amount_idr' => $grossClaim,
                'retention_deduction_idr' => $retentionDeduction,
                'net_payable_amount_idr' => $netPayable,
                'supervising_consultant_name' => $data['supervising_consultant_name'] ?? 'PT Rekayasa Konsultan Pengawas',
                'certified_at' => now(),
                'status' => 'approved',
            ]);

            $project->update([
                'actual_physical_progress_pct' => $newCumulative,
                'accumulated_cip_cost_idr' => $project->accumulated_cip_cost_idr + $grossClaim,
                'status' => $newCumulative >= 100.0 ? 'bast_issued' : 'in_progress',
            ]);

            // Post ledger entry for CIP asset recognition and contractor payable
            if ($grossClaim > 0) {
                $lines = Posting::lines()->debit('asset:epc:construction_in_progress:IDR', $grossClaim);
                if ($retentionDeduction > 0) {
                    $lines->credit('ap:epc:contractor_retention:IDR', $retentionDeduction);
                }
                if ($netPayable > 0) {
                    $lines->credit('ap:epc:contractor_payable:IDR', $netPayable);
                }

                $this->ledgerService->post(new PostingDTO(
                    type: EpcTransactionTypes::EPC_PROGRESS_CERTIFICATE->value,
                    description: "EPC Monthly Certificate {$certificate->certificate_number} CIP cost for {$project->project_code}",
                    idempotencyKey: "EPC-MC-{$certificate->certificate_number}",
                    entries: $lines->toEntries('IDR'),
                    referenceType: 'EPC_CERTIFICATE',
                    referenceId: (string) $certificate->id,
                ));
            }

            return [
                'certificate' => $certificate,
                'project' => $project->fresh(),
                'gross_claim_idr' => $grossClaim,
                'retention_idr' => $retentionDeduction,
                'net_payable_idr' => $netPayable,
            ];
        });
    }
}
