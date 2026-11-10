<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Simulation;

/**
 * Profiler performa waktu eksekusi dan konsumsi memori per Tick Handler simulasi.
 */
final class VirtualTickPerformanceProfiler
{
    /** @var array<string, array{total_ms: float, executions: int, peak_mem_delta: int}> */
    private array $profiles = [];

    public function recordExecution(string $handlerName, float $durationMs, int $memoryDeltaBytes): void
    {
        if (! isset($this->profiles[$handlerName])) {
            $this->profiles[$handlerName] = [
                'total_ms' => 0.0,
                'executions' => 0,
                'peak_mem_delta' => 0,
            ];
        }

        $this->profiles[$handlerName]['total_ms'] += $durationMs;
        $this->profiles[$handlerName]['executions']++;
        if ($memoryDeltaBytes > $this->profiles[$handlerName]['peak_mem_delta']) {
            $this->profiles[$handlerName]['peak_mem_delta'] = $memoryDeltaBytes;
        }
    }

    /**
     * @return array<string, array{executions: int, total_ms: float, avg_ms: float, peak_mem_kb: float}>
     */
    public function getSummary(): array
    {
        $summary = [];

        foreach ($this->profiles as $name => $data) {
            $avg = $data['executions'] > 0 ? ($data['total_ms'] / $data['executions']) : 0.0;
            $summary[$name] = [
                'executions' => $data['executions'],
                'total_ms' => round($data['total_ms'], 3),
                'avg_ms' => round($avg, 3),
                'peak_mem_kb' => round($data['peak_mem_delta'] / 1024, 2),
            ];
        }

        return $summary;
    }

    public function reset(): void
    {
        $this->profiles = [];
    }
}
