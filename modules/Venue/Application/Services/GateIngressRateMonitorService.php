<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Venue\Domain\Models\GateScanLog;
use Modules\Venue\Domain\Models\TurnstileDevice;

final class GateIngressRateMonitorService
{
    /**
     * Measure ingress throughput across all turnstiles in the last N minutes.
     *
     * @return array{
     *     window_minutes: int,
     *     total_scans: int,
     *     rate_per_minute: float,
     *     lane_metrics: array<string, array{turnstile_code: string, scans: int, scans_per_min: float, congestion_level: string}>,
     *     recommended_action: string
     * }
     */
    public function calculateIngressVelocity(int $venueId, int $windowMinutes = 5): array
    {
        $since = CarbonImmutable::now()->subMinutes($windowMinutes);

        $scans = GateScanLog::where('created_at', '>=', $since)
            ->where('scan_result', 'GRANTED')
            ->where('direction', 'IN')
            ->get();

        $totalScans = $scans->count();
        $ratePerMinute = $windowMinutes > 0 ? round($totalScans / $windowMinutes, 2) : 0.0;

        $devices = TurnstileDevice::where('is_active', true)->get();
        $laneMetrics = [];
        $maxLaneRate = 0.0;

        foreach ($devices as $dev) {
            $laneScans = $scans->where('turnstile_code', $dev->device_code)->count();
            $laneRate = $windowMinutes > 0 ? round($laneScans / $windowMinutes, 2) : 0.0;

            if ($laneRate > $maxLaneRate) {
                $maxLaneRate = $laneRate;
            }

            $congestion = 'OPTIMAL';
            if ($laneRate > 25.0) {
                $congestion = 'CRITICAL';
            } elseif ($laneRate > 15.0) {
                $congestion = 'HEAVY';
            } elseif ($laneRate > 5.0) {
                $congestion = 'MODERATE';
            }

            $laneMetrics[$dev->device_code] = [
                'turnstile_code' => $dev->device_code,
                'scans' => $laneScans,
                'scans_per_min' => $laneRate,
                'congestion_level' => $congestion,
            ];
        }

        $recommendation = 'Arus gerbang lancar. Seluruh lajur beroperasi normal.';
        if ($maxLaneRate > 25.0) {
            $recommendation = 'PERINGATAN: Antrean gerbang kritis (>25 orang/menit). Buka lajur sekunder atau turnstile cadangan.';
        } elseif ($maxLaneRate > 15.0) {
            $recommendation = 'Kepadatan tinggi. Staf gerbang diinstruksikan mempercepat verifikasi tiket.';
        }

        return [
            'window_minutes' => $windowMinutes,
            'total_scans' => $totalScans,
            'rate_per_minute' => $ratePerMinute,
            'lane_metrics' => $laneMetrics,
            'recommended_action' => $recommendation,
        ];
    }

    /**
     * Detect uneven queue distribution among turnstile lanes.
     *
     * @return array<string, string>
     */
    public function detectImbalancedLanes(array $laneMetrics): array
    {
        $redirects = [];
        $overloaded = [];
        $underutilized = [];

        foreach ($laneMetrics as $code => $metric) {
            if ($metric['congestion_level'] === 'CRITICAL' || $metric['congestion_level'] === 'HEAVY') {
                $overloaded[] = $code;
            } elseif ($metric['congestion_level'] === 'OPTIMAL') {
                $underutilized[] = $code;
            }
        }

        if (!empty($overloaded) && !empty($underutilized)) {
            foreach ($overloaded as $idx => $busyLane) {
                $freeLane = $underutilized[$idx % count($underutilized)];
                $redirects[$busyLane] = "Arahkan sebagian tamu dari lajur {$busyLane} ke lajur bebas {$freeLane}";
            }
        }

        return $redirects;
    }
}
