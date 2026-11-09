<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Canary;

use Closure;

final class SyntheticCanaryProbeRunner
{
    /**
     * Executes synthetic active probe requests to canary pods and calculates latency & error response.
     *
     * @param  list<string>  $probeUris
     * @return array{total_probes: int, successful_probes: int, failed_probes: int, mean_latency_ms: float}
     */
    public function runProbes(array $probeUris, Closure $httpSender): array
    {
        $successful = 0;
        $failed = 0;
        $latencies = [];

        foreach ($probeUris as $uri) {
            $start = microtime(true);
            try {
                $status = (int) $httpSender($uri);
                $elapsed = (microtime(true) - $start) * 1000.0;
                $latencies[] = $elapsed;

                if ($status >= 200 && $status < 400) {
                    $successful++;
                } else {
                    $failed++;
                }
            } catch (\Throwable $e) {
                $failed++;
            }
        }

        $total = count($probeUris);
        $meanLatency = $latencies !== [] ? round(array_sum($latencies) / count($latencies), 2) : 0.0;

        return [
            'total_probes' => $total,
            'successful_probes' => $successful,
            'failed_probes' => $failed,
            'mean_latency_ms' => $meanLatency,
        ];
    }
}
