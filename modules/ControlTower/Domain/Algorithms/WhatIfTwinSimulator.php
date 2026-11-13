<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Algorithms;

use InvalidArgumentException;

final class WhatIfTwinSimulator
{
    /**
     * @return array{
     *     shock_type: string,
     *     baseline_otif_bps: int,
     *     simulated_otif_bps: int,
     *     at_risk_orders_count: int,
     *     estimated_margin_delta_idr: int
     * }
     */
    public function simulate(
        string $shockType,
        int $shockMagnitudeBps,
        int $durationDays,
        int $baselineOtifBps,
        int $activeOrderCount,
        int $dailyGrossMarginIdr,
    ): array {
        if ($durationDays < 1 || $baselineOtifBps < 0 || $baselineOtifBps > 10_000) {
            throw new InvalidArgumentException('INVALID_TWIN_INPUT: Parameter simulasi digital twin tidak valid.');
        }

        $normalized = strtoupper($shockType);
        $otifImpactBps = match ($normalized) {
            'PORT_CLOSURE' => min($baselineOtifBps, intdiv($shockMagnitudeBps * $durationDays, 10)),
            'MATERIAL_INFLATION' => intdiv($shockMagnitudeBps, 20),
            'SUPPLY_DELAY' => min($baselineOtifBps, intdiv($shockMagnitudeBps * $durationDays, 12)),
            'DEMAND_SPIKE', 'DEMAND_SURGE' => min($baselineOtifBps, intdiv($shockMagnitudeBps, 4)),
            'SUPPLIER_LOSS' => min($baselineOtifBps, intdiv($shockMagnitudeBps * $durationDays, 8)),
            'WAREHOUSE_CAPACITY' => min($baselineOtifBps, intdiv($shockMagnitudeBps * $durationDays, 14)),
            'PLANT_EXPANSION' => -min(10_000 - $baselineOtifBps, intdiv($shockMagnitudeBps, 5)),
            default => intdiv($shockMagnitudeBps, 10),
        };

        $simulatedOtifBps = max(0, min(10_000, $baselineOtifBps - $otifImpactBps));
        $otifDropBps = max(0, $baselineOtifBps - $simulatedOtifBps);
        $atRiskOrders = intdiv($activeOrderCount * $otifDropBps + 9999, 10_000);

        $marginDeltaIdr = match ($normalized) {
            'PLANT_EXPANSION' => intdiv($dailyGrossMarginIdr * $durationDays * $shockMagnitudeBps, 10_000),
            default => -intdiv($dailyGrossMarginIdr * $durationDays * max(0, $shockMagnitudeBps), 10_000),
        };

        return [
            'shock_type' => $normalized,
            'baseline_otif_bps' => $baselineOtifBps,
            'simulated_otif_bps' => $simulatedOtifBps,
            'at_risk_orders_count' => $atRiskOrders,
            'estimated_margin_delta_idr' => $marginDeltaIdr,
        ];
    }
}
