<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Canary;

final class WilsonScoreConfidenceCalculator
{
    /**
     * Calculates Wilson score binomial confidence interval (e.g. 95% confidence z=1.96, 99% z=2.576).
     *
     * @return array{lower_bound: float, center: float, upper_bound: float, margin_of_error: float}
     */
    public function calculateInterval(int $positiveCount, int $totalSample, float $zValue = 1.96): array
    {
        if ($totalSample <= 0) {
            return [
                'lower_bound' => 0.0,
                'center' => 0.0,
                'upper_bound' => 0.0,
                'margin_of_error' => 0.0,
            ];
        }

        $p = (float) $positiveCount / (float) $totalSample;
        $n = (float) $totalSample;
        $z2 = $zValue * $zValue;

        $denominator = 1.0 + ($z2 / $n);
        $centerAdjusted = $p + ($z2 / (2.0 * $n));

        $underRadical = ($p * (1.0 - $p) / $n) + ($z2 / (4.0 * $n * $n));
        $spread = $zValue * sqrt(max(0.0, $underRadical));

        $lower = max(0.0, ($centerAdjusted - $spread) / $denominator);
        $upper = min(1.0, ($centerAdjusted + $spread) / $denominator);
        $center = $centerAdjusted / $denominator;

        return [
            'lower_bound' => round($lower, 6),
            'center' => round($center, 6),
            'upper_bound' => round($upper, 6),
            'margin_of_error' => round(($upper - $lower) / 2.0, 6),
        ];
    }
}
