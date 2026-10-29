<?php

declare(strict_types=1);

namespace Modules\Core\Application\Wave3;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Shared\Application\BaseAction;

class ReinsuranceTreatyCatastropheAndTakafulSettlementAction extends BaseAction
{
    public function __construct(
        protected ?Ledger $ledger = null
    ) {}

    /**
     * Executes actuarial underwriting, Quota-Share & Excess-of-Loss (XoL) reinsurance treaty waterfall,
     * RBC solvency guardrails, CAT parametric triggers, embedded 30-line broker splits, and Takaful Tabarru settlement (Fase 156-160).
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        return DB::transaction(function () use ($params): array {
            $settlementCode = (string) ($params['settlement_code'] ?? 'W3-REINS-001');

            // 1. Fase 156-157: RBC Solvency Capital Adequacy Ratio (>= 120.00% = 12000 bps)
            $rbcRatioBps = (int) ($params['rbc_ratio_bps'] ?? 18500);
            $minimumRbcBps = (int) ($params['minimum_rbc_bps'] ?? 12000);
            if ($rbcRatioBps < $minimumRbcBps) {
                throw new DomainException("Underwriting & treaty settlement halted: RBC solvency ratio {$rbcRatioBps} bps is below regulatory minimum {$minimumRbcBps} bps");
            }

            // 2. Quota-Share + Excess-of-Loss (XoL) Reinsurance Treaty Waterfall
            $grossClaimMinor = (int) ($params['gross_claim_minor'] ?? 500000000);
            $quotaShareCessionBps = (int) ($params['quota_share_cession_bps'] ?? 3000); // 30%
            $xolRetentionCapMinor = (int) ($params['xol_retention_cap_minor'] ?? 200000000);
            $xolLayerLimitMinor = (int) ($params['xol_layer_limit_minor'] ?? 250000000);

            if ($grossClaimMinor < 0 || $quotaShareCessionBps < 0 || $quotaShareCessionBps > 10000) {
                throw new DomainException('Invalid reinsurance treaty claim or quota-share basis points');
            }

            $quotaCededMinor = intdiv($grossClaimMinor * $quotaShareCessionBps, 10000);
            $netAfterQuotaMinor = $grossClaimMinor - $quotaCededMinor;
            $xolCededMinor = min($xolLayerLimitMinor, max(0, $netAfterQuotaMinor - $xolRetentionCapMinor));
            $retainedClaimMinor = $grossClaimMinor - $quotaCededMinor - $xolCededMinor;
            $totalReinsuranceRecoverableMinor = $quotaCededMinor + $xolCededMinor;

            // 3. Fase 158-159: Embedded 30-Line Broker Commission & Wellness Discount
            $grossPremiumMinor = (int) ($params['gross_premium_minor'] ?? 100000000);
            $brokerCommissionBps = (int) ($params['broker_commission_bps'] ?? 1000); // 10%
            $wellnessDiscountBps = min(1500, max(0, (int) ($params['wellness_discount_bps'] ?? 500)));
            $netEffectivePremiumMinor = $grossPremiumMinor - intdiv($grossPremiumMinor * $wellnessDiscountBps, 10000);
            $brokerCommissionMinor = intdiv($netEffectivePremiumMinor * $brokerCommissionBps, 10000);

            // 4. Fase 160: Takaful Tabarru' Pool & Qardh Hasan Deficit Guard
            $tabarruContributionMinor = (int) ($params['tabarru_contribution_minor'] ?? 80000000);
            $wakalahFeeBps = (int) ($params['wakalah_fee_bps'] ?? 1500);
            $wakalahFeeMinor = intdiv($tabarruContributionMinor * $wakalahFeeBps, 10000);
            $netTabarruPoolMinor = $tabarruContributionMinor - $wakalahFeeMinor;
            $tabarruClaimsPaidMinor = (int) ($params['tabarru_claims_paid_minor'] ?? 50000000);

            $tabarruBalanceMinor = $netTabarruPoolMinor - $tabarruClaimsPaidMinor;
            $qardhHasanInjectionMinor = 0;
            $participantSurplusShareMinor = 0;
            $operatorSurplusShareMinor = 0;

            if ($tabarruBalanceMinor < 0) {
                $qardhHasanInjectionMinor = abs($tabarruBalanceMinor);
            } elseif ($tabarruBalanceMinor > 0) {
                $participantShareBps = (int) ($params['participant_surplus_bps'] ?? 7000);
                $participantSurplusShareMinor = intdiv($tabarruBalanceMinor * $participantShareBps, 10000);
                $operatorSurplusShareMinor = $tabarruBalanceMinor - $participantSurplusShareMinor;
            }

            $catMagnitudeScaled = (int) ($params['cat_richter_scaled_10'] ?? 0);
            $catThresholdScaled = (int) ($params['cat_trigger_threshold_scaled_10'] ?? 65);
            $catParametricState = $catMagnitudeScaled >= $catThresholdScaled && $catMagnitudeScaled > 0
                ? 'CAT_PARAMETRIC_TRIGGERED'
                : 'NORMAL_LOSS_REGIME';

            $previousHash = (string) ($params['previous_hash'] ?? hash('sha256', 'GENESIS-WAVE3-REINSURANCE'));
            $treatyChainHash = hash('sha256', implode('|', [
                $settlementCode,
                (string) $grossClaimMinor,
                (string) $retainedClaimMinor,
                (string) $totalReinsuranceRecoverableMinor,
                (string) $qardhHasanInjectionMinor,
                $previousHash,
            ]));

            if ($totalReinsuranceRecoverableMinor > 0 && $this->ledger !== null) {
                $idempotencyKey = hash('sha256', "W3:REINS:CESSION:{$settlementCode}:{$totalReinsuranceRecoverableMinor}");
                $this->ledger->post(new PostingDTO(
                    type: 'WAVE3_REINSURANCE_TAKAFUL',
                    description: "Reinsurance treaty cession & Takaful reserve settlement {$settlementCode}",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('wave3:reinsurance_recoverable:IDR', $totalReinsuranceRecoverableMinor)
                        ->credit('wave3:takaful_tabarru_pool:IDR', $totalReinsuranceRecoverableMinor)
                        ->toEntries('IDR'),
                    referenceType: 'WAVE3_REINSURANCE_TREATY',
                    referenceId: $settlementCode,
                ));
            }

            $result = [
                'settlement_code' => $settlementCode,
                'rbc_ratio_bps' => $rbcRatioBps,
                'gross_claim_minor' => $grossClaimMinor,
                'quota_ceded_minor' => $quotaCededMinor,
                'xol_ceded_minor' => $xolCededMinor,
                'total_reinsurance_recoverable_minor' => $totalReinsuranceRecoverableMinor,
                'retained_claim_minor' => $retainedClaimMinor,
                'net_effective_premium_minor' => $netEffectivePremiumMinor,
                'broker_commission_minor' => $brokerCommissionMinor,
                'wakalah_fee_minor' => $wakalahFeeMinor,
                'net_tabarru_pool_minor' => $netTabarruPoolMinor,
                'qardh_hasan_injection_minor' => $qardhHasanInjectionMinor,
                'participant_surplus_share_minor' => $participantSurplusShareMinor,
                'operator_surplus_share_minor' => $operatorSurplusShareMinor,
                'cat_parametric_state' => $catParametricState,
                'treaty_chain_hash' => $treatyChainHash,
            ];

            $this->audit(
                action: 'core.wave3.reinsurance_and_takaful_settled',
                newValues: [
                    'settlement_code' => $settlementCode,
                    'gross_claim_minor' => $grossClaimMinor,
                    'recoverable_minor' => $totalReinsuranceRecoverableMinor,
                    'cat_state' => $catParametricState,
                ],
                correlationId: "w3_reins_{$settlementCode}",
                impactType: 'reinsurance_takaful'
            );

            $this->outbox(
                eventType: 'Wave3ReinsuranceTakafulSettled',
                payload: [
                    'settlement_code' => $settlementCode,
                    'recoverable_minor' => $totalReinsuranceRecoverableMinor,
                    'treaty_chain_hash' => $treatyChainHash,
                ],
                idempotencyKey: hash('sha256', "OUTBOX:W3:REINS:{$settlementCode}")
            );

            return $result;
        });
    }
}
