<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Modules\Venue\Domain\Models\GateScanLog;

final class VenueGatePassbackAuditReportService
{
    /**
     * Generate an audit breakdown of anti-passback and ticket sharing attempts per turnstile lane.
     *
     * @return array{
     *     total_gate_scans: int,
     *     granted_admissions: int,
     *     passback_violations_blocked: int,
     *     underage_rejections: int,
     *     violation_rate_bps: int,
     *     highest_violation_lane: string,
     *     suspicious_ticket_tokens: list<string>
     * }
     */
    public function generatePassbackAudit(int $eventId): array
    {
        $allScans = GateScanLog::where('event_id', $eventId)->get();
        $total = $allScans->count();

        $granted = $allScans->where('scan_result', 'GRANTED')->count();
        $passbackViolations = $allScans->filter(static function ($s): bool {
            return str_contains(strtolower((string) $s->rejection_reason), 'passback');
        })->count();

        $underage = $allScans->filter(static function ($s): bool {
            return str_contains(strtolower((string) $s->rejection_reason), 'usia') ||
                str_contains(strtolower((string) $s->rejection_reason), 'umur');
        })->count();

        $violationRateBps = $total > 0 ? (int) round(($passbackViolations / $total) * 10000) : 0;

        $laneStats = [];
        foreach ($allScans as $scan) {
            $lane = (string) ($scan->turnstile_code ?? 'MAIN');
            if (!isset($laneStats[$lane])) {
                $laneStats[$lane] = 0;
            }
            if (str_contains(strtolower((string) $scan->rejection_reason), 'passback')) {
                $laneStats[$lane]++;
            }
        }

        arsort($laneStats);
        $highestLane = !empty($laneStats) ? (string) array_key_first($laneStats) : 'NONE';

        $suspiciousTickets = $allScans->filter(static function ($s): bool {
            return str_contains(strtolower((string) $s->rejection_reason), 'passback');
        })->pluck('ticket_id')->unique()->map(static fn ($id): string => "TKT-ID-{$id}")->values()->take(10)->all();

        return [
            'total_gate_scans' => $total,
            'granted_admissions' => $granted,
            'passback_violations_blocked' => $passbackViolations,
            'underage_rejections' => $underage,
            'violation_rate_bps' => $violationRateBps,
            'highest_violation_lane' => $highestLane,
            'suspicious_ticket_tokens' => $suspiciousTickets,
        ];
    }
}
