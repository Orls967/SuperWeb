<?php

declare(strict_types=1);

namespace Modules\Core\Application\Wave3;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Shared\Application\BaseAction;

class ForestrySvlkChainOfCustodyAndCircularEprCreditAction extends BaseAction
{
    public function __construct(
        protected ?Ledger $ledger = null
    ) {}

    /**
     * Enforces SVLK timber chain-of-custody legality, Annual Allowable Cut (AAC/RKT) ceilings,
     * peatland zero-encroachment, biodiversity nature-finance valuation, and Circular EPR anti-double-count credits (Fase 172-175).
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        return DB::transaction(function () use ($params): array {
            $custodyCode = (string) ($params['custody_code'] ?? 'W3-SVLK-EPR-001');

            // 1. Fase 172: Forestry SVLK Annual Allowable Cut (AAC) & Protected Peatland Zero-Encroachment
            $harvestedTimberM3 = (int) ($params['harvested_timber_m3'] ?? 1200);
            $annualAllowableCutM3 = (int) ($params['annual_allowable_cut_m3'] ?? 1500);
            $protectedPeatlandOverlapM2 = (int) ($params['protected_peatland_overlap_m2'] ?? 0);

            if ($protectedPeatlandOverlapM2 > 0) {
                throw new DomainException("Forestry SVLK violation: harvesting plot overlaps {$protectedPeatlandOverlapM2} m2 of protected peatland/conservation zone");
            }

            if ($harvestedTimberM3 > $annualAllowableCutM3) {
                throw new DomainException("Forestry AAC violation: harvested timber ({$harvestedTimberM3} m3) exceeds concession RKT/AAC limit ({$annualAllowableCutM3} m3)");
            }

            $sawnTimberOutputM3 = (int) ($params['sawn_timber_output_m3'] ?? 840);
            if ($sawnTimberOutputM3 > $harvestedTimberM3) {
                throw new DomainException('Sawmill chain-of-custody volume violation: sawn timber output cannot exceed roundwood log input');
            }

            // 2. Fase 173: Nature Finance Biodiversity & Restoration Credits
            $restoredHectares = (int) ($params['restored_hectares'] ?? 250);
            $natureCreditRatePerHaMinor = (int) ($params['nature_credit_rate_per_ha_minor'] ?? 400000);
            $natureFinanceCreditMinor = $restoredHectares * $natureCreditRatePerHaMinor;

            // 3. Fase 174-175: Circular Economy & Extended Producer Responsibility (EPR) Anti-Double-Count
            $recycledLotId = (string) ($params['recycled_lot_id'] ?? 'EPR-LOT-2026-001');
            /** @var list<string> $alreadyRetiredLotIds */
            $alreadyRetiredLotIds = $params['already_retired_lot_ids'] ?? [];
            if (in_array($recycledLotId, $alreadyRetiredLotIds, true)) {
                throw new DomainException("Circular EPR double-counting blocked: recycled lot '{$recycledLotId}' has already been retired");
            }

            $packagingPlacedKg = (int) ($params['packaging_placed_on_market_kg'] ?? 50000);
            $mandatoryRecoveryTargetBps = (int) ($params['mandatory_recovery_target_bps'] ?? 6000); // 60%
            $eprObligationKg = intdiv($packagingPlacedKg * $mandatoryRecoveryTargetBps, 10000);
            $verifiedRecycledKg = (int) ($params['verified_recycled_kg'] ?? 35000);

            $surplusRecycledKg = max(0, $verifiedRecycledKg - $eprObligationKg);
            $shortfallKg = max(0, $eprObligationKg - $verifiedRecycledKg);
            $eprPricePerKgMinor = (int) ($params['epr_credit_price_per_kg_minor'] ?? 3500);
            $circularCreditValueMinor = $surplusRecycledKg * $eprPricePerKgMinor;
            $eprComplianceLevyMinor = $shortfallKg * $eprPricePerKgMinor;

            $totalSettlementMinor = $natureFinanceCreditMinor + $circularCreditValueMinor;

            $previousHash = (string) ($params['previous_hash'] ?? hash('sha256', 'GENESIS-WAVE3-SVLK-EPR'));
            $svlkCustodyHash = hash('sha256', implode('|', [
                $custodyCode,
                $recycledLotId,
                (string) $harvestedTimberM3,
                (string) $sawnTimberOutputM3,
                (string) $verifiedRecycledKg,
                $previousHash,
            ]));

            if ($totalSettlementMinor > 0 && $this->ledger !== null) {
                $idempotencyKey = hash('sha256', "W3:SVLKEPR:SETTLE:{$custodyCode}:{$totalSettlementMinor}");
                $this->ledger->post(new PostingDTO(
                    type: 'WAVE3_FORESTRY_SVLK_EPR',
                    description: "Forestry SVLK nature credit & Circular EPR settlement {$custodyCode}",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('wave3:forestry_epr_receivable:IDR', $totalSettlementMinor)
                        ->credit('wave3:forestry_circular_credit_revenue:IDR', $totalSettlementMinor)
                        ->toEntries('IDR'),
                    referenceType: 'WAVE3_FORESTRY_CIRCULAR',
                    referenceId: $custodyCode,
                ));
            }

            $result = [
                'custody_code' => $custodyCode,
                'svlk_legality_status' => 'SVLK_VERIFIED_LEGAL_TIMBER',
                'remaining_aac_m3' => $annualAllowableCutM3 - $harvestedTimberM3,
                'sawmill_recovery_bps' => $harvestedTimberM3 > 0 ? intdiv($sawnTimberOutputM3 * 10000, $harvestedTimberM3) : 0,
                'nature_finance_credit_minor' => $natureFinanceCreditMinor,
                'epr_obligation_kg' => $eprObligationKg,
                'surplus_recycled_kg' => $surplusRecycledKg,
                'shortfall_kg' => $shortfallKg,
                'circular_credit_value_minor' => $circularCreditValueMinor,
                'epr_compliance_levy_minor' => $eprComplianceLevyMinor,
                'total_settlement_minor' => $totalSettlementMinor,
                'svlk_custody_hash' => $svlkCustodyHash,
            ];

            $this->audit(
                action: 'core.wave3.forestry_svlk_and_circular_epr_settled',
                newValues: [
                    'custody_code' => $custodyCode,
                    'total_settlement_minor' => $totalSettlementMinor,
                    'svlk_custody_hash' => $svlkCustodyHash,
                ],
                correlationId: "w3_svlk_{$custodyCode}",
                impactType: 'forestry_circular_esg'
            );

            $this->outbox(
                eventType: 'Wave3ForestrySvlkAndEprSettled',
                payload: [
                    'custody_code' => $custodyCode,
                    'recycled_lot_id' => $recycledLotId,
                    'svlk_custody_hash' => $svlkCustodyHash,
                ],
                idempotencyKey: hash('sha256', "OUTBOX:W3:SVLK:{$custodyCode}")
            );

            return $result;
        });
    }
}
