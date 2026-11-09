<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Canary;

final class CanaryTrafficAnomalyDetector
{
    /**
     * @param  list<array{timestamp: string, error_count: int, total_count: int, p99_latency_ms: float}>  $timeseries
     * @return array{
     *     anomaly_detected: bool,
     *     severity: string,
     *     anomalies: list<array{type: string, message: string, timestamp: string}>,
     *     burn_rate_multiple: float
     * }
     */
    public function detectAnomalies(array $timeseries, float $sloErrorTarget = 0.005): array
    {
        $anomalies = [];
        $burnRateMultiple = 1.0;

        if (count($timeseries) < 3) {
            return [
                'anomaly_detected' => false,
                'severity' => 'none',
                'anomalies' => [],
                'burn_rate_multiple' => 1.0,
            ];
        }

        $totalErrors = 0;
        $totalRequests = 0;

        foreach ($timeseries as $sample) {
            $err = $sample['error_count'] ?? 0;
            $tot = $sample['total_count'] ?? 0;
            $latency = $sample['p99_latency_ms'] ?? 0.0;
            $ts = $sample['timestamp'] ?? '';

            $totalErrors += $err;
            $totalRequests += $tot;

            // Immediate sample error spike (> 5%)
            if ($tot > 50 && ($err / $tot) > 0.05) {
                $pct = round(($err / $tot) * 100, 2);
                $anomalies[] = [
                    'type' => 'HIGH_ERROR_SPIKE',
                    'message' => "Error spike detected: {$pct}% ({$err}/{$tot}) at {$ts}",
                    'timestamp' => $ts,
                ];
            }

            // High latency degradation (> 2000ms)
            if ($latency > 2000.0) {
                $anomalies[] = [
                    'type' => 'LATENCY_DEGRADATION',
                    'message' => "High latency p99 of {$latency}ms exceeds 2000ms threshold at {$ts}",
                    'timestamp' => $ts,
                ];
            }
        }

        if ($totalRequests > 0 && $sloErrorTarget > 0) {
            $observedRate = $totalErrors / $totalRequests;
            $burnRateMultiple = round($observedRate / $sloErrorTarget, 2);

            if ($burnRateMultiple >= 14.4) {
                $anomalies[] = [
                    'type' => 'CRITICAL_SLO_BURN_RATE',
                    'message' => "1-hour error budget burn rate is {$burnRateMultiple}x normal",
                    'timestamp' => end($timeseries)['timestamp'] ?? '',
                ];
            }
        }

        $severity = 'none';
        if (count($anomalies) > 0) {
            $hasCritical = array_filter($anomalies, fn ($a) => str_contains($a['type'], 'CRITICAL') || str_contains($a['type'], 'HIGH'));
            $severity = ! empty($hasCritical) ? 'critical' : 'warning';
        }

        return [
            'anomaly_detected' => count($anomalies) > 0,
            'severity' => $severity,
            'anomalies' => $anomalies,
            'burn_rate_multiple' => $burnRateMultiple,
        ];
    }
}
