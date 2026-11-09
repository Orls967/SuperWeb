<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Release;

final class DoraMetricsCalculator
{
    /**
     * Calculates the 4 key DORA metrics from deployment and incident telemetry.
     *
     * @param  list<array{deployed_at: string, lead_time_hours: float, is_failure: bool, restore_duration_minutes: float}>  $deployments
     * @return array{deployment_frequency_per_week: float, median_lead_time_hours: float, change_failure_rate_pct: float, mttr_minutes: float, performance_tier: string}
     */
    public function calculateDora(array $deployments, int $windowDays = 30): array
    {
        if ($deployments === []) {
            return [
                'deployment_frequency_per_week' => 0.0,
                'median_lead_time_hours' => 0.0,
                'change_failure_rate_pct' => 0.0,
                'mttr_minutes' => 0.0,
                'performance_tier' => 'LOW',
            ];
        }

        $totalCount = count($deployments);
        $weeks = max(1.0, $windowDays / 7.0);
        $frequency = round($totalCount / $weeks, 2);

        $leadTimes = array_column($deployments, 'lead_time_hours');
        sort($leadTimes);
        $medianLeadTime = $leadTimes[(int) floor(count($leadTimes) / 2)] ?? 0.0;

        $failures = 0;
        $totalRestoreMinutes = 0.0;

        foreach ($deployments as $d) {
            if ($d['is_failure'] ?? false) {
                $failures++;
                $totalRestoreMinutes += ($d['restore_duration_minutes'] ?? 0.0);
            }
        }

        $cfr = round(($failures / $totalCount) * 100.0, 2);
        $mttr = $failures > 0 ? round($totalRestoreMinutes / $failures, 1) : 0.0;

        // DORA Elite: Deploy frequency > 7/week, Lead time < 24h, CFR < 5%, MTTR < 60m
        $tier = 'MEDIUM';
        if ($frequency >= 7.0 && $medianLeadTime <= 24.0 && $cfr <= 5.0 && $mttr <= 60.0) {
            $tier = 'ELITE';
        } elseif ($frequency >= 1.0 && $medianLeadTime <= 168.0 && $cfr <= 15.0) {
            $tier = 'HIGH';
        }

        return [
            'deployment_frequency_per_week' => $frequency,
            'median_lead_time_hours' => round((float) $medianLeadTime, 2),
            'change_failure_rate_pct' => $cfr,
            'mttr_minutes' => $mttr,
            'performance_tier' => $tier,
        ];
    }
}
