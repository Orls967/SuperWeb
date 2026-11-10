<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Simulation;

use Modules\Core\Domain\Models\SimRun;

/**
 * Layanan telemetri streaming metrik sesi simulasi (throughput, latensi, pemakaian memori).
 */
final class SimulationTelemetryStreamService
{
    /**
     * @return array{
     *     timestamp_iso: string,
     *     run_id: string,
     *     current_day: int,
     *     total_days: int,
     *     progress_pct: float,
     *     memory_usage_mb: float,
     *     peak_memory_mb: float,
     *     events_dispatched: int,
     *     ticks_executed: int
     * }
     */
    public function captureTelemetryFrame(
        SimRun $run,
        int $eventsDispatched = 0,
        int $ticksExecuted = 0,
    ): array {
        $progress = $run->total_days > 0
            ? round(($run->current_day / $run->total_days) * 100, 2)
            : 0.0;

        $memMb = round(memory_get_usage(true) / (1024 * 1024), 2);
        $peakMemMb = round(memory_get_peak_usage(true) / (1024 * 1024), 2);

        return [
            'timestamp_iso' => now()->toIso8601String(),
            'run_id' => $run->id,
            'current_day' => $run->current_day,
            'total_days' => $run->total_days,
            'progress_pct' => $progress,
            'memory_usage_mb' => $memMb,
            'peak_memory_mb' => $peakMemMb,
            'events_dispatched' => $eventsDispatched,
            'ticks_executed' => $ticksExecuted,
        ];
    }
}
