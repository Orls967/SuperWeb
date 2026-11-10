<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\Simulation;

use Modules\Core\Domain\Models\SimCheckpoint;
use Modules\Core\Domain\Models\SimRun;

final class PruneCheckpointsAction
{
    /**
     * Memangkas checkpoint lama, mempertahankan N checkpoint terakhir serta checkpoint milestone kelipatan M.
     */
    public function execute(string $runId, int $keepLast = 5, int $milestoneInterval = 30): int
    {
        $run = SimRun::where('id', $runId)->orWhere('name', $runId)->firstOrFail();

        $allCheckpoints = SimCheckpoint::where('run_id', $run->id)
            ->orderBy('day_number', 'desc')
            ->get();

        $deletedCount = 0;
        $seen = 0;

        foreach ($allCheckpoints as $cp) {
            $seen++;
            // Simpan N checkpoint terbaru
            if ($seen <= $keepLast) {
                continue;
            }

            // Simpan checkpoint milestone (misal hari 30, 60, 90, 365)
            if ($cp->day_number % $milestoneInterval === 0 || $cp->day_number === $run->total_days) {
                continue;
            }

            $cp->delete();
            $deletedCount++;
        }

        return $deletedCount;
    }
}
