<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Canary;

final class CanaryTrafficShapingPlanner
{
    /**
     * Standard progressive rollout stepping profile
     *
     * @var list<array{step: int, traffic_percent: int, soak_duration_minutes: int}>
     */
    private const DEFAULT_ROLLOUT_PROFILE = [
        ['step' => 1, 'traffic_percent' => 5, 'soak_duration_minutes' => 15],
        ['step' => 2, 'traffic_percent' => 25, 'soak_duration_minutes' => 30],
        ['step' => 3, 'traffic_percent' => 50, 'soak_duration_minutes' => 30],
        ['step' => 4, 'traffic_percent' => 100, 'soak_duration_minutes' => 60],
    ];

    /**
     * @return array{
     *     profile_name: string,
     *     total_steps: int,
     *     total_bake_duration_minutes: int,
     *     steps: list<array{step: int, traffic_percent: int, soak_duration_minutes: int}>
     * }
     */
    public function generateRolloutPlan(string $riskTier = 'medium'): array
    {
        $steps = match ($riskTier) {
            'critical' => [
                ['step' => 1, 'traffic_percent' => 1, 'soak_duration_minutes' => 30],
                ['step' => 2, 'traffic_percent' => 5, 'soak_duration_minutes' => 45],
                ['step' => 3, 'traffic_percent' => 20, 'soak_duration_minutes' => 60],
                ['step' => 4, 'traffic_percent' => 50, 'soak_duration_minutes' => 60],
                ['step' => 5, 'traffic_percent' => 100, 'soak_duration_minutes' => 120],
            ],
            'high' => [
                ['step' => 1, 'traffic_percent' => 5, 'soak_duration_minutes' => 20],
                ['step' => 2, 'traffic_percent' => 20, 'soak_duration_minutes' => 40],
                ['step' => 3, 'traffic_percent' => 50, 'soak_duration_minutes' => 45],
                ['step' => 4, 'traffic_percent' => 100, 'soak_duration_minutes' => 60],
            ],
            default => self::DEFAULT_ROLLOUT_PROFILE,
        };

        $totalDuration = array_sum(array_column($steps, 'soak_duration_minutes'));

        return [
            'profile_name' => "canary_profile_{$riskTier}",
            'total_steps' => count($steps),
            'total_bake_duration_minutes' => $totalDuration,
            'steps' => $steps,
        ];
    }

    /**
     * @param  list<array{step: int, traffic_percent: int, soak_duration_minutes: int}>  $steps
     * @return array{step: int, traffic_percent: int, soak_duration_minutes: int}|null
     */
    public function getNextStep(array $steps, int $currentTrafficPercent): ?array
    {
        foreach ($steps as $step) {
            if ($step['traffic_percent'] > $currentTrafficPercent) {
                return $step;
            }
        }

        return null;
    }
}
