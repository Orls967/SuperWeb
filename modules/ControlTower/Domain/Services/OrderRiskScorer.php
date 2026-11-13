<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Services;

final class OrderRiskScorer
{
    public const MAX_SHORTFALL_WEIGHT = 400;

    public const MAX_LEAD_TIME_WEIGHT = 250;

    public const MAX_SHELF_LIFE_WEIGHT = 200;

    public const MAX_TRANSIT_WEIGHT = 150;

    /**
     * Evaluates deterministic supply/order risk score (0..1000) across 4 weighted integer factors.
     *
     * @return array{
     *     item_code: string,
     *     risk_score: int,
     *     risk_tier: string,
     *     shortfall_component: int,
     *     lead_time_component: int,
     *     shelf_life_component: int,
     *     transit_component: int,
     *     factors_json: array<string, mixed>,
     *     mitigation_plan: string
     * }
     */
    public function score(
        string $itemCode,
        int $requestedQty,
        int $availableSupplyQty,
        int $safetyStockQty = 0,
        int $leadTimeSigmaDays = 0,
        int $onTimePermil = 1000,
        int $leadTimeSamples = 0,
        int $oldestLotAgeDays = 0,
        int $onHandQty = 0,
        int $quarantineQty = 0,
        int $holdQty = 0,
        int $overdueShipmentsCount = 0,
        int $maxHoursInTransit = 0,
        int $slaTransitHours = 72,
    ): array {
        $safeSupply = max(0, $availableSupplyQty);
        $shortfallBaseQty = $requestedQty > 0 ? $requestedQty : max(0, $safetyStockQty);
        $shortfallQty = $shortfallBaseQty > 0 ? max(0, $shortfallBaseQty - $safeSupply) : 0;

        $shortfallComponent = $shortfallBaseQty > 0
            ? min(self::MAX_SHORTFALL_WEIGHT, intdiv($shortfallQty * self::MAX_SHORTFALL_WEIGHT, $shortfallBaseQty))
            : 0;

        if ($leadTimeSamples <= 0) {
            $leadTimeComponent = 0;
        } else {
            $sigmaPart = min(125, intdiv(max(0, $leadTimeSigmaDays) * 125, 10));
            $latePermil = max(0, 1000 - min(1000, max(0, $onTimePermil)));
            $reliabilityPart = intdiv($latePermil * 125, 1000);
            $leadTimeComponent = min(self::MAX_LEAD_TIME_WEIGHT, $sigmaPart + $reliabilityPart);
        }

        $agePart = min(self::MAX_SHELF_LIFE_WEIGHT, intdiv(max(0, $oldestLotAgeDays) * self::MAX_SHELF_LIFE_WEIGHT, 180));
        $blockedQty = max(0, $quarantineQty) + max(0, $holdQty);
        $blockedPart = ($onHandQty > 0 && $blockedQty > 0)
            ? min(50, intdiv($blockedQty * 50, $onHandQty))
            : 0;
        $shelfLifeComponent = min(self::MAX_SHELF_LIFE_WEIGHT, $agePart + $blockedPart);

        if ($overdueShipmentsCount <= 0) {
            $transitComponent = 0;
        } else {
            $safeSla = max(1, $slaTransitHours);
            $countPart = min(90, $overdueShipmentsCount * 30);
            $excessHours = max(0, $maxHoursInTransit - $safeSla);
            $hoursPart = min(60, intdiv($excessHours * 60, $safeSla));
            $transitComponent = min(self::MAX_TRANSIT_WEIGHT, $countPart + $hoursPart);
        }

        $riskScore = min(1000, max(0, $shortfallComponent + $leadTimeComponent + $shelfLifeComponent + $transitComponent));

        $riskTier = match (true) {
            $riskScore >= 750 => 'CRITICAL',
            $riskScore >= 500 => 'HIGH',
            $riskScore >= 250 => 'MEDIUM',
            default => 'LOW',
        };

        $mitigationPlan = $this->buildMitigationPlan(
            itemCode: $itemCode,
            riskTier: $riskTier,
            shortfallComponent: $shortfallComponent,
            shortfallQty: $shortfallQty,
            leadTimeComponent: $leadTimeComponent,
            leadTimeSigmaDays: $leadTimeSigmaDays,
            onTimePermil: $onTimePermil,
            shelfLifeComponent: $shelfLifeComponent,
            oldestLotAgeDays: $oldestLotAgeDays,
            transitComponent: $transitComponent,
            overdueShipmentsCount: $overdueShipmentsCount,
            maxHoursInTransit: $maxHoursInTransit,
        );

        return [
            'item_code' => $itemCode,
            'risk_score' => $riskScore,
            'risk_tier' => $riskTier,
            'shortfall_component' => $shortfallComponent,
            'lead_time_component' => $leadTimeComponent,
            'shelf_life_component' => $shelfLifeComponent,
            'transit_component' => $transitComponent,
            'factors_json' => [
                'shortfall' => [
                    'component_score' => $shortfallComponent,
                    'max_weight' => self::MAX_SHORTFALL_WEIGHT,
                    'requested_qty' => $requestedQty,
                    'safety_stock_qty' => $safetyStockQty,
                    'available_supply_qty' => $safeSupply,
                    'shortfall_qty' => $shortfallQty,
                ],
                'lead_time' => [
                    'component_score' => $leadTimeComponent,
                    'max_weight' => self::MAX_LEAD_TIME_WEIGHT,
                    'sigma_days' => $leadTimeSigmaDays,
                    'on_time_permil' => $onTimePermil,
                    'samples' => $leadTimeSamples,
                ],
                'shelf_life' => [
                    'component_score' => $shelfLifeComponent,
                    'max_weight' => self::MAX_SHELF_LIFE_WEIGHT,
                    'oldest_lot_age_days' => $oldestLotAgeDays,
                    'on_hand_qty' => $onHandQty,
                    'quarantine_qty' => $quarantineQty,
                    'hold_qty' => $holdQty,
                ],
                'transit' => [
                    'component_score' => $transitComponent,
                    'max_weight' => self::MAX_TRANSIT_WEIGHT,
                    'overdue_shipments_count' => $overdueShipmentsCount,
                    'max_hours_in_transit' => $maxHoursInTransit,
                    'sla_transit_hours' => $slaTransitHours,
                ],
                'total_score' => $riskScore,
            ],
            'mitigation_plan' => $mitigationPlan,
        ];
    }

    private function buildMitigationPlan(
        string $itemCode,
        string $riskTier,
        int $shortfallComponent,
        int $shortfallQty,
        int $leadTimeComponent,
        int $leadTimeSigmaDays,
        int $onTimePermil,
        int $shelfLifeComponent,
        int $oldestLotAgeDays,
        int $transitComponent,
        int $overdueShipmentsCount,
        int $maxHoursInTransit,
    ): string {
        $steps = [];

        if ($shortfallComponent >= 100 && $shortfallQty > 0) {
            $steps[] = "Realokasi stok antar-node atau percepat produksi CTP/PO darurat untuk menutup shortfall {$shortfallQty} unit ({$itemCode}).";
        }
        if ($leadTimeComponent >= 60) {
            $steps[] = "Diversifikasi pemasok dan tinjau buffer safety stock (sigma {$leadTimeSigmaDays} hari, on-time {$onTimePermil}‰).";
        }
        if ($shelfLifeComponent >= 60) {
            $steps[] = "Prioritaskan FEFO untuk lot tertua ({$oldestLotAgeDays} hari) serta inspeksi stok karantina/hold.";
        }
        if ($transitComponent >= 30) {
            $steps[] = "Eskalasi kurir untuk {$overdueShipmentsCount} pengiriman in-transit terlambat (maks {$maxHoursInTransit} jam).";
        }

        if ($steps === []) {
            return "Risiko {$riskTier} untuk {$itemCode}: pertahankan pemantauan rutin pasokan, lot, dan SLA pengiriman.";
        }

        return implode(' ', $steps);
    }
}
