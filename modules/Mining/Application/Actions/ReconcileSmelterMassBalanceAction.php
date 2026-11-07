<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Modules\Mining\Domain\Models\MiningSmelterFurnaceRun;

final class ReconcileSmelterMassBalanceAction
{
    /**
     * @return array{
     *     run_code: string,
     *     total_feed_dmt: float,
     *     metal_output_tons: float,
     *     slag_output_tons: float,
     *     flue_dust_tons: float,
     *     theoretical_yield_pct: float,
     *     actual_yield_pct: float,
     *     yield_variance_pct: float,
     *     is_reconciled: bool
     * }
     */
    public function execute(MiningSmelterFurnaceRun $run): array
    {
        $feedDmt = max(0.1, (float) $run->ore_feed_dmt);
        $metalTons = (float) $run->metal_output_tons;
        $slagTons = (float) $run->slag_output_tons;

        $totalSolidProducts = $metalTons + $slagTons;
        $flueDustTons = max(0.0, ($feedDmt + (float) $run->coal_reductant_tons + (float) $run->silica_flux_tons) - $totalSolidProducts);

        $actualYieldPct = round(($metalTons / $feedDmt) * 100.0, 2);
        $theoreticalYieldPct = round(((float) $run->ore_ni_grade_pct / 12.5) * 100.0 * 0.925, 2);
        $variancePct = round($actualYieldPct - $theoreticalYieldPct, 2);

        $isReconciled = abs($variancePct) <= 2.5;

        return [
            'run_code' => $run->run_code,
            'total_feed_dmt' => $feedDmt,
            'metal_output_tons' => $metalTons,
            'slag_output_tons' => $slagTons,
            'flue_dust_tons' => round($flueDustTons, 2),
            'theoretical_yield_pct' => $theoreticalYieldPct,
            'actual_yield_pct' => $actualYieldPct,
            'yield_variance_pct' => $variancePct,
            'is_reconciled' => $isReconciled,
        ];
    }
}
