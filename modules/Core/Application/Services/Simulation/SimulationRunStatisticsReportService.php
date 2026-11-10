<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Simulation;

use Modules\Core\Domain\Models\SimCheckpoint;
use Modules\Core\Domain\Models\SimRun;
use Modules\Core\Domain\Models\SimTwinSnapshot;

/**
 * Layanan pembuat laporan komprehensif statistik akhir sesi simulasi.
 */
final class SimulationRunStatisticsReportService
{
    /**
     * @return array<string, mixed>
     */
    public function generateReport(SimRun $run): array
    {
        $checkpoints = SimCheckpoint::where('run_id', $run->id)->get();
        $snapshots = SimTwinSnapshot::where('run_id', $run->id)->get();

        $handlerFrequency = [];
        foreach ($checkpoints as $cp) {
            $hashes = (array) ($cp->handler_hashes ?? []);
            foreach (array_keys($hashes) as $hName) {
                $handlerFrequency[$hName] = ($handlerFrequency[$hName] ?? 0) + 1;
            }
        }

        return [
            'run_id' => $run->id,
            'name' => $run->name,
            'status' => $run->status,
            'mode' => $run->mode,
            'seed' => $run->seed,
            'current_day' => $run->current_day,
            'total_days' => $run->total_days,
            'chain_hash' => $run->chain_hash,
            'checkpoint_count' => $checkpoints->count(),
            'snapshot_count' => $snapshots->count(),
            'handler_checkpoint_frequency' => $handlerFrequency,
            'created_at' => $run->created_at?->toIso8601String(),
            'completed_at' => $run->completed_at?->toIso8601String(),
        ];
    }
}
