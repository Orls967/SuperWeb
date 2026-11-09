<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Telemetry;

/**
 * Pengumpul telemetri runtime service, konsumsi memori, dan laju throughput per modul.
 */
class ServiceTelemetryCollector
{
    /**
     * @return array{memory_usage_mb: float, memory_peak_mb: float, php_version: string, server_time: string}
     */
    public function collectRuntimeMetrics(): array
    {
        $memUsage = memory_get_usage(true) / 1024 / 1024;
        $memPeak = memory_get_peak_usage(true) / 1024 / 1024;

        return [
            'memory_usage_mb' => round($memUsage, 2),
            'memory_peak_mb' => round($memPeak, 2),
            'php_version' => PHP_VERSION,
            'server_time' => date('c'),
        ];
    }

    /**
     * @param string $serviceName
     * @param float $latencyMs
     * @param bool $isError
     * @return array<string, mixed>
     */
    public function recordSample(string $serviceName, float $latencyMs, bool $isError): array
    {
        return [
            'service' => $serviceName,
            'latency_ms' => round($latencyMs, 2),
            'is_error' => $isError,
            'timestamp' => microtime(true),
        ];
    }
}

