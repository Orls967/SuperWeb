<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Actuarial;

use InvalidArgumentException;

/**
 * Chain-Ladder (Development Method) for Actuarial IBNR Reserving (KONSEP.md Lini 18).
 *
 * Implements deterministic calculation of link ratios (development factors)
 * and projected ultimate losses from a cumulative loss development triangle.
 */
final class ChainLadder
{
    /**
     * Estimates ultimate losses and IBNR reserves from a cumulative loss triangle.
     *
     * @param  array<int, array<int, int>>  $cumulativeTriangle  Matrix of cumulative losses [accident_year_idx => [dev_year_idx => amount_idr]]
     * @return array{
     *     development_factors: array<int, float>,
     *     projected_triangle: array<int, array<int, int>>,
     *     ultimate_losses: array<int, int>,
     *     latest_losses: array<int, int>,
     *     ibnr_reserves: array<int, int>,
     *     total_ibnr_reserve: int
     * }
     */
    public static function estimateIbnr(array $cumulativeTriangle): array
    {
        $n = count($cumulativeTriangle);
        if ($n < 2) {
            throw new InvalidArgumentException('Loss triangle must have at least 2 origin years.');
        }

        // 1. Calculate age-to-age development factors (f_j)
        $developmentFactors = [];
        for ($j = 0; $j < $n - 1; $j++) {
            $sumCurrent = 0;
            $sumNext = 0;

            for ($i = 0; $i < $n - 1 - $j; $i++) {
                if (isset($cumulativeTriangle[$i][$j], $cumulativeTriangle[$i][$j + 1])) {
                    $sumCurrent += $cumulativeTriangle[$i][$j];
                    $sumNext += $cumulativeTriangle[$i][$j + 1];
                }
            }

            if ($sumCurrent <= 0) {
                $developmentFactors[$j] = 1.0;
            } else {
                $developmentFactors[$j] = $sumNext / $sumCurrent;
            }
        }

        // 2. Project full square & calculate ultimate losses
        $projectedTriangle = $cumulativeTriangle;
        $ultimateLosses = [];
        $latestLosses = [];
        $ibnrReserves = [];
        $totalIbnr = 0;

        for ($i = 0; $i < $n; $i++) {
            $latestDevIdx = count($cumulativeTriangle[$i]) - 1;
            $latestAmount = $cumulativeTriangle[$i][$latestDevIdx] ?? 0;
            $latestLosses[$i] = $latestAmount;

            $currentProjected = $latestAmount;
            for ($j = $latestDevIdx; $j < $n - 1; $j++) {
                $factor = $developmentFactors[$j] ?? 1.0;
                $currentProjected = (int) round($currentProjected * $factor);
                $projectedTriangle[$i][$j + 1] = $currentProjected;
            }

            $ultimateLosses[$i] = $currentProjected;
            $ibnr = max(0, $currentProjected - $latestAmount);
            $ibnrReserves[$i] = $ibnr;
            $totalIbnr += $ibnr;
        }

        return [
            'development_factors' => $developmentFactors,
            'projected_triangle' => $projectedTriangle,
            'ultimate_losses' => $ultimateLosses,
            'latest_losses' => $latestLosses,
            'ibnr_reserves' => $ibnrReserves,
            'total_ibnr_reserve' => $totalIbnr,
        ];
    }
}
