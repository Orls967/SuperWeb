<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Simulation;

use Modules\Core\Domain\Models\SimCheckpoint;
use Modules\Core\Domain\Models\SimRun;
use Modules\Core\Domain\Models\SimTwinState;

/**
 * Layanan analitik dan kalkulasi metrik ringkasan eksekusi sesi simulasi.
 */
final class SimulationMetricsCalculatorService
{
    /**
     * @return array{
     *     total_runs: int,
     *     running_runs: int,
     *     completed_runs: int,
     *     sandbox_runs: int,
     *     total_checkpoints: int,
     *     total_twin_states: int,
     *     average_days_per_run: float,
     *     success_rate_pct: float
     * }
     */
    public function getSystemSimulationOverview(): array
    {
        $totalRuns = SimRun::count();
        $runningRuns = SimRun::where('status', 'running')->count();
        $completedRuns = SimRun::where('status', 'completed')->count();
        $sandboxRuns = SimRun::where('mode', 'sandbox')->count();
        $totalCheckpoints = SimCheckpoint::count();
        $totalTwinStates = SimTwinState::count();

        $avgDays = $totalRuns > 0 ? (float) SimRun::avg('current_day') : 0.0;
        $successRate = $totalRuns > 0 ? round(($completedRuns / $totalRuns) * 100, 2) : 100.0;

        return [
            'total_runs' => $totalRuns,
            'running_runs' => $runningRuns,
            'completed_runs' => $completedRuns,
            'sandbox_runs' => $sandboxRuns,
            'total_checkpoints' => $totalCheckpoints,
            'total_twin_states' => $totalTwinStates,
            'average_days_per_run' => round($avgDays, 2),
            'success_rate_pct' => $successRate,
        ];
    }

    /**
     * Menghitung kecepatan eksekusi (throughput hari simulasi per detik riil).
     */
    public function calculateThroughput(SimRun $run, float $realDurationSeconds): float
    {
        if ($realDurationSeconds <= 0.0001) {
            return (float) $run->current_day;
        }

        return round($run->current_day / $realDurationSeconds, 2);
    }
}
