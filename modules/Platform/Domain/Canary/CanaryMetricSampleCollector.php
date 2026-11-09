<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Canary;

final class CanaryMetricSampleCollector
{
    /**
     * Aggregates raw HTTP status codes from access logs into baseline and canary error counts.
     *
     * @param  list<array{status_code: int, is_canary: bool, latency_ms: float}>  $accessLogs
     * @return array{baseline_requests: int, baseline_errors: int, canary_requests: int, canary_errors: int, canary_p99_latency_ms: float}
     */
    public function collectFromLogs(array $accessLogs): array
    {
        $baseReq = 0;
        $baseErr = 0;
        $canReq = 0;
        $canErr = 0;
        $canLatencies = [];

        foreach ($accessLogs as $entry) {
            $code = (int) ($entry['status_code'] ?? 200);
            $isCanary = (bool) ($entry['is_canary'] ?? false);
            $latency = (float) ($entry['latency_ms'] ?? 0.0);
            $isError = ($code >= 500);

            if ($isCanary) {
                $canReq++;
                if ($isError) {
                    $canErr++;
                }
                $canLatencies[] = $latency;
            } else {
                $baseReq++;
                if ($isError) {
                    $baseErr++;
                }
            }
        }

        sort($canLatencies);
        $p99Index = (int) floor(0.99 * count($canLatencies));
        $p99Latency = $canLatencies[$p99Index] ?? 0.0;

        return [
            'baseline_requests' => $baseReq,
            'baseline_errors' => $baseErr,
            'canary_requests' => $canReq,
            'canary_errors' => $canErr,
            'canary_p99_latency_ms' => round($p99Latency, 2),
        ];
    }
}
