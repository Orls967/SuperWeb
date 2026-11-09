<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Canary;

final class MultiVariantCanaryRouter
{
    /**
     * Staged promotion tiers for canary traffic rollouts.
     *
     * @var list<int>
     */
    private const STAGES = [1, 5, 25, 50, 100];

    /**
     * Determines the next traffic percentage stage based on observation maturity and health.
     */
    public function getNextStage(int $currentTrafficPct, int $sampleSize, float $zScore): int
    {
        if ($zScore >= 3.0) {
            return 0; // Trigger instant halt / rollback
        }

        $currentIndex = array_search($currentTrafficPct, self::STAGES, true);
        if ($currentIndex === false) {
            return self::STAGES[0];
        }

        // Each progressive stage requires higher sample significance
        $requiredSamples = [
            1 => 500,
            5 => 2000,
            25 => 5000,
            50 => 10000,
            100 => 20000,
        ];

        $threshold = $requiredSamples[$currentTrafficPct] ?? 1000;
        if ($sampleSize >= $threshold && $zScore < 1.96) {
            $nextIndex = min(count(self::STAGES) - 1, $currentIndex + 1);

            return self::STAGES[$nextIndex];
        }

        return $currentTrafficPct; // Stay on current stage
    }
}
