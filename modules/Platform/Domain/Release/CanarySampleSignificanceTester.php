<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Release;

final class CanarySampleSignificanceTester
{
    /**
     * Determines whether enough sample requests have been observed to establish statistical significance.
     *
     * @param  int  $sampleCount  e.g. 1500
     * @param  float  $expectedRate  e.g. 0.005 (0.5%)
     * @return array{is_significant: bool, min_required_samples: int, statistical_power_pct: float}
     */
    public function testSignificance(int $sampleCount, float $expectedRate = 0.01): array
    {
        // Rule of thumb for binomial proportion test: n * p >= 10 and n * (1-p) >= 10
        $rate = max(0.0001, $expectedRate);
        $minRequired = (int) ceil(10.0 / $rate);

        $isSignificant = ($sampleCount >= $minRequired);
        $power = min(99.9, ($sampleCount / max(1, $minRequired)) * 80.0);

        return [
            'is_significant' => $isSignificant,
            'min_required_samples' => $minRequired,
            'statistical_power_pct' => round($power, 1),
        ];
    }
}
