<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Shared\Application\BaseAction;

class SettleReinsuranceTreatyCessionAndTakafulTabarruAction extends BaseAction
{
    public function __construct(
        protected ?Ledger $ledger = null
    ) {}

    /**
     * Owner-module Insurance action for Lini 18 Reinsurance Treaty Quota-Share/XoL cession,
     * RBC capital floor enforcement, and Takaful Tabarru surplus/deficit Qardh Hasan settlement.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        return DB::transaction(function () use ($params): array {
            $policyTreatyCode = (string) ($params['policy_treaty_code'] ?? 'INS-W3-TREATY-001');
            $rbcRatioBps = (int) ($params['rbc_ratio_bps'] ?? 16000);
            if ($rbcRatioBps < 12000) {
                throw new DomainException("Insurance RBC ratio {$rbcRatioBps} bps violates 12000 bps regulatory solvency floor");
            }

            $grossClaimMinor = (int) ($params['gross_claim_minor'] ?? 300000000);
            $quotaShareBps = (int) ($params['quota_share_bps'] ?? 2500);
            $xolAttachmentMinor = (int) ($params['xol_attachment_minor'] ?? 150000000);

            $quotaCededMinor = intdiv($grossClaimMinor * $quotaShareBps, 10000);
            $afterQuotaMinor = $grossClaimMinor - $quotaCededMinor;
            $xolCededMinor = max(0, $afterQuotaMinor - $xolAttachmentMinor);
            $netRetainedMinor = $grossClaimMinor - $quotaCededMinor - $xolCededMinor;
            $totalCededMinor = $quotaCededMinor + $xolCededMinor;

            $tabarruPoolMinor = (int) ($params['tabarru_pool_minor'] ?? 120000000);
            $tabarruClaimsMinor = (int) ($params['tabarru_claims_minor'] ?? 90000000);
            $qardhHasanLoanMinor = $tabarruClaimsMinor > $tabarruPoolMinor ? ($tabarruClaimsMinor - $tabarruPoolMinor) : 0;
            $distributableSurplusMinor = $tabarruPoolMinor > $tabarruClaimsMinor ? ($tabarruPoolMinor - $tabarruClaimsMinor) : 0;

            if ($totalCededMinor > 0 && $this->ledger !== null) {
                $this->ledger->post(new PostingDTO(
                    type: 'WAVE3_REINSURANCE_TAKAFUL',
                    description: "Insurance owner-module treaty cession {$policyTreatyCode}",
                    idempotencyKey: hash('sha256', "INS:W3:TREATY:{$policyTreatyCode}:{$totalCededMinor}"),
                    entries: Posting::lines()
                        ->debit('wave3:reinsurance_recoverable:IDR', $totalCededMinor)
                        ->credit('wave3:takaful_tabarru_pool:IDR', $totalCededMinor)
                        ->toEntries('IDR'),
                    referenceType: 'INS_REINSURANCE_TREATY',
                    referenceId: $policyTreatyCode,
                ));
            }

            $hash = hash('sha256', "{$policyTreatyCode}:{$grossClaimMinor}:{$totalCededMinor}:{$netRetainedMinor}");

            $this->audit(
                action: 'ins.wave3.treaty_and_takaful_settled',
                newValues: ['policy_treaty_code' => $policyTreatyCode, 'total_ceded_minor' => $totalCededMinor],
                correlationId: "ins_w3_{$policyTreatyCode}",
                impactType: 'insurance_reinsurance'
            );

            return [
                'policy_treaty_code' => $policyTreatyCode,
                'gross_claim_minor' => $grossClaimMinor,
                'quota_ceded_minor' => $quotaCededMinor,
                'xol_ceded_minor' => $xolCededMinor,
                'total_ceded_minor' => $totalCededMinor,
                'net_retained_minor' => $netRetainedMinor,
                'qardh_hasan_loan_minor' => $qardhHasanLoanMinor,
                'distributable_surplus_minor' => $distributableSurplusMinor,
                'treaty_hash' => $hash,
            ];
        });
    }
}
