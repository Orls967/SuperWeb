<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;

/**
 * ProfitabilityCostIntelligenceService (Fase 212)
 *
 * Implements:
 *  - 212.1 Profitability hierarchy reconciliation (Unit aggregate == Entity profit)
 *  - 212.4 Margin bridge balancing across volume, mix, price, cost, and FX components
 */
class ProfitabilityCostIntelligenceService
{
    /**
     * Reconcile entity profitability against business unit aggregates.
     */
    public function recordProfitabilityHierarchy(string $entity, float $entityProfit, array $unitProfits): object
    {
        $unitsSum = round(array_sum($unitProfits), 2);
        $diff = round(abs($entityProfit - $unitsSum), 2);
        if ($diff > 0.0) {
            throw new \RuntimeException("Profitability hierarchy discrepancy: Entity profit IDR {$entityProfit} != units sum IDR {$unitsSum}.");
        }

        $code = 'HIERARCHY-'.strtoupper($entity);

        DB::table('fin_profitability_hierarchies')->updateOrInsert(
            ['hierarchy_code' => $code],
            [
                'entity_code' => strtoupper($entity),
                'reported_entity_profit_idr' => $entityProfit,
                'aggregated_units_profit_idr' => $unitsSum,
                'hierarchy_discrepancy_idr' => 0.00,
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('fin_profitability_hierarchies')->where('hierarchy_code', $code)->first();
    }

    /**
     * Build margin bridge ensuring exact decomposition into variance drivers.
     */
    public function recordMarginBridge(string $period, float $volume, float $mix, float $price, float $cost, float $fx, float $actualDelta): object
    {
        $sumDrivers = round($volume + $mix + $price + $cost + $fx, 2);
        $diff = round(abs($actualDelta - $sumDrivers), 2);
        if ($diff > 0.0) {
            throw new \RuntimeException("Margin bridge unbalanced: Sum of drivers IDR {$sumDrivers} != actual delta IDR {$actualDelta}.");
        }

        $code = 'MB-'.strtoupper($period);

        DB::table('fin_margin_bridges')->updateOrInsert(
            ['bridge_code' => $code],
            [
                'period_code' => strtoupper($period),
                'volume_variance_idr' => $volume,
                'mix_variance_idr' => $mix,
                'price_variance_idr' => $price,
                'cost_variance_idr' => $cost,
                'fx_variance_idr' => $fx,
                'total_actual_margin_delta_idr' => $actualDelta,
                'unexplained_bridge_variance_idr' => 0.00,
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('fin_margin_bridges')->where('bridge_code', $code)->first();
    }

    /**
     * Quality audit gate (`group:audit`).
     */
    public function audit(): array
    {
        $hierarchyDiscrepancies = DB::table('fin_profitability_hierarchies')
            ->where('hierarchy_discrepancy_idr', '!=', 0.0)
            ->count();

        $marginDiscrepancies = DB::table('fin_margin_bridges')
            ->where('unexplained_bridge_variance_idr', '!=', 0.0)
            ->count();

        $discrepancies = $hierarchyDiscrepancies + $marginDiscrepancies;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_hierarchies' => DB::table('fin_profitability_hierarchies')->count(),
            'total_margin_bridges' => DB::table('fin_margin_bridges')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
