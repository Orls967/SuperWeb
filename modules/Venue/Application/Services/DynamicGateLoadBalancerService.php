<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

final class DynamicGateLoadBalancerService
{
    public const MAX_CONSECUTIVE_SCAN_ERRORS = 3;

    /**
     * Determine optimal turnstile lane for arriving guest based on load and reader health.
     *
     * @param list<array{lane_code: string, is_online: bool, queue_length: int, error_streak: int}> $lanes
     * @return array{
     *     recommended_lane: string,
     *     estimated_wait_seconds: int,
     *     fallback_to_manual_steward: bool,
     *     routing_rationale: string
     * }
     */
    public function balanceArrivingGuest(array $lanes): array
    {
        $eligibleLanes = array_filter(
            $lanes,
            static fn (array $l): bool => $l['is_online'] && $l['error_streak'] < self::MAX_CONSECUTIVE_SCAN_ERRORS
        );

        if (empty($eligibleLanes)) {
            return [
                'recommended_lane' => 'MANUAL_STEWARD_BOOTH',
                'estimated_wait_seconds' => 120,
                'fallback_to_manual_steward' => true,
                'routing_rationale' => 'Semua turnstile otomatis mengalami kendala teknis atau antrean kritis.',
            ];
        }

        // Sort by shortest queue
        usort($eligibleLanes, static fn (array $a, array $b): int => $a['queue_length'] <=> $b['queue_length']);
        $best = $eligibleLanes[0];
        $waitSec = (int) ($best['queue_length'] * 4); // ~4 detik per scan turnstile

        return [
            'recommended_lane' => $best['lane_code'],
            'estimated_wait_seconds' => $waitSec,
            'fallback_to_manual_steward' => false,
            'routing_rationale' => "Lajur {$best['lane_code']} memiliki antrean terpendek ({$best['queue_length']} orang).",
        ];
    }

    /**
     * Track error streaks and trigger auto-lockout if optical scanner is malfunctioning.
     */
    public function evaluateScannerHealth(int $consecutiveErrors): array
    {
        $shouldLockout = $consecutiveErrors >= self::MAX_CONSECUTIVE_SCAN_ERRORS;
        return [
            'consecutive_errors' => $consecutiveErrors,
            'should_lockout' => $shouldLockout,
            'status' => $shouldLockout ? 'HARDWARE_FAULT_ISOLATED' : 'HEALTHY',
        ];
    }
}
