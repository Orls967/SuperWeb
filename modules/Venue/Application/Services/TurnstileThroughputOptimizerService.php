<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Carbon\Carbon;
use Modules\Venue\Domain\Models\GateTurnstile;

/**
 * Service to analyze and optimize physical gate turnstile throughput.
 * Detects mechanical jams, badge presentation latency, and enforces anti-passback rules.
 */
class TurnstileThroughputOptimizerService
{
    public const MAX_HEALTHY_CYCLE_SECONDS = 4.0;

    public const MIN_PERSONS_PER_MINUTE_CAPACITY = 15;

    /**
     * Evaluate turnstile telemetry frame and detect hardware bottlenecks.
     *
     * @param  array<int, array{timestamp: int, rotation_duration_sec: float, scan_status: string, badge_id: string}>  $recentScans
     * @return array{
     *     turnstile_id: int,
     *     hardware_identifier: string,
     *     mean_cycle_seconds: float,
     *     throughput_ppm: float,
     *     mechanical_jam_risk: string,
     *     anti_passback_violations_count: int,
     *     operational_directive: string
     * }
     */
    public function evaluateTurnstilePerformance(
        GateTurnstile $turnstile,
        array $recentScans
    ): array {
        $count = count($recentScans);

        if ($count === 0) {
            return [
                'turnstile_id' => $turnstile->id,
                'hardware_identifier' => $turnstile->hardware_identifier,
                'mean_cycle_seconds' => 0.0,
                'throughput_ppm' => 0.0,
                'mechanical_jam_risk' => 'IDLE',
                'anti_passback_violations_count' => 0,
                'operational_directive' => 'IDLE_STANDBY',
            ];
        }

        $totalCycleSec = 0.0;
        $seenBadges = [];
        $antiPassbackViolations = 0;
        $jamIndicators = 0;

        foreach ($recentScans as $scan) {
            $cycleSec = (float) $scan['rotation_duration_sec'];
            $totalCycleSec += $cycleSec;

            if ($cycleSec > self::MAX_HEALTHY_CYCLE_SECONDS) {
                $jamIndicators++;
            }

            $badge = (string) $scan['badge_id'];
            if (isset($seenBadges[$badge])) {
                $deltaSec = $scan['timestamp'] - $seenBadges[$badge];
                // Anti-passback: identical badge scanned within 60 seconds
                if ($deltaSec < 60) {
                    $antiPassbackViolations++;
                }
            }
            $seenBadges[$badge] = $scan['timestamp'];
        }

        $meanCycleSec = round($totalCycleSec / $count, 2);
        $throughputPpm = $meanCycleSec > 0.0 ? round(60.0 / $meanCycleSec, 1) : 0.0;

        $jamRisk = 'NORMAL';
        if ($jamIndicators >= 3 || $meanCycleSec > 6.0) {
            $jamRisk = 'HIGH_MECHANICAL_JAM_SUSPECTED';
        } elseif ($jamIndicators > 0) {
            $jamRisk = 'MODERATE_SLUGGISH_ROTATION';
        }

        $directive = 'CONTINUE_OPERATION';
        if ($jamRisk === 'HIGH_MECHANICAL_JAM_SUSPECTED') {
            $directive = 'DISPATCH_MAINTENANCE_SWITCH_TO_MANUAL_BYPASS';
        } elseif ($antiPassbackViolations > 2) {
            $directive = 'ALERT_SECURITY_SERIAL_BADGE_PASSBACK_DETECTED';
        }

        return [
            'turnstile_id' => $turnstile->id,
            'hardware_identifier' => $turnstile->hardware_identifier,
            'mean_cycle_seconds' => $meanCycleSec,
            'throughput_ppm' => $throughputPpm,
            'mechanical_jam_risk' => $jamRisk,
            'anti_passback_violations_count' => $antiPassbackViolations,
            'operational_directive' => $directive,
        ];
    }

    /**
     * Bulk calibrate gate array for ingress surge mode.
     */
    public function configureGateArraySurgeCadence(string $zoneIdentifier, bool $enableSurgeMode): int
    {
        $turnstiles = GateTurnstile::all();
        $updatedCount = 0;

        foreach ($turnstiles as $turnstile) {
            $turnstile->update([
                'is_locked' => ! $enableSurgeMode,
                'updated_at' => Carbon::now(),
            ]);
            $updatedCount++;
        }

        return $updatedCount;
    }
}
