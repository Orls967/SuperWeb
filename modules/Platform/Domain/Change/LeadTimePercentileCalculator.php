<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Change;

final class LeadTimePercentileCalculator
{
    /**
     * Calculates p50, p90, and p99 lead times for changes in hours.
     *
     * @param  list<float>  $leadTimeHours
     * @return array{p50: float, p90: float, p99: float, min: float, max: float, mean: float}
     */
    public function calculatePercentiles(array $leadTimeHours): array
    {
        if ($leadTimeHours === []) {
            return ['p50' => 0.0, 'p90' => 0.0, 'p99' => 0.0, 'min' => 0.0, 'max' => 0.0, 'mean' => 0.0];
        }

        sort($leadTimeHours);
        $count = count($leadTimeHours);

        $getPercentile = function (float $p) use ($leadTimeHours, $count): float {
            $index = (int) floor(($p / 100.0) * ($count - 1));

            return $leadTimeHours[$index] ?? 0.0;
        };

        $sum = array_sum($leadTimeHours);
        $mean = $count > 0 ? round($sum / $count, 2) : 0.0;

        return [
            'p50' => round($getPercentile(50.0), 2),
            'p90' => round($getPercentile(90.0), 2),
            'p99' => round($getPercentile(99.0), 2),
            'min' => round(min($leadTimeHours), 2),
            'max' => round(max($leadTimeHours), 2),
            'mean' => $mean,
        ];
    }
}
