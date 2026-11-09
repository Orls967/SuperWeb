<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Canary;

final class SloBurnRateImpactPredictor
{
    /**
     * Projects the impact on 30-day SLO error budget if canary error rate is promoted to 100% traffic.
     *
     * @param  float  $targetSloAvailability  e.g. 0.999 (99.9% availability, 0.1% budget)
     * @param  float  $observedCanaryErrorRate  e.g. 0.005 (0.5% error rate)
     * @return array{error_budget_percentage: float, projected_burn_rate: float, time_to_exhaust_hours: float, triggers_sre_blocker: bool}
     */
    public function predictImpact(float $targetSloAvailability, float $observedCanaryErrorRate): array
    {
        $allowedErrorRate = 1.0 - $targetSloAvailability; // e.g. 0.001
        if ($allowedErrorRate <= 0.0) {
            $allowedErrorRate = 0.0001;
        }

        // Burn rate = observed error rate / allowed error rate
        $burnRate = $observedCanaryErrorRate / $allowedErrorRate;

        // 30 days = 720 hours
        $timeToExhaustHours = $burnRate > 0.0 ? (720.0 / $burnRate) : 99999.0;

        // SRE policy: If 1 hour burn rate > 14.4x (2% budget burned in 1 hour) or 6 hours > 6x, block promotion
        $triggersBlocker = $burnRate > 6.0 || $timeToExhaustHours < 120.0;

        return [
            'error_budget_percentage' => round($allowedErrorRate * 100.0, 4),
            'projected_burn_rate' => round($burnRate, 2),
            'time_to_exhaust_hours' => round($timeToExhaustHours, 1),
            'triggers_sre_blocker' => $triggersBlocker,
        ];
    }
}
