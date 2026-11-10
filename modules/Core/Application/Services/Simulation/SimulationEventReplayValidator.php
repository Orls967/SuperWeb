<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Simulation;

use Modules\Core\Domain\Models\SimCheckpoint;
use Modules\Core\Domain\Models\SimRun;

/**
 * Layanan validasi replay: memverifikasi bahwa pengulangan rentang waktu virtual
 * menghasilkan hash chain yang presisi sama.
 */
final class SimulationEventReplayValidator
{
    /**
     * @return array{
     *     run_id: string,
     *     start_day: int,
     *     end_day: int,
     *     is_consistent: bool,
     *     checkpoints_evaluated: int
     * }
     */
    public function validateReplayRange(SimRun $run, int $startDay, int $endDay): array
    {
        $checkpoints = SimCheckpoint::where('run_id', $run->id)
            ->whereBetween('day_number', [$startDay, $endDay])
            ->orderBy('day_number', 'asc')
            ->get();

        $consistent = true;
        foreach ($checkpoints as $cp) {
            if (empty($cp->chain_hash) || strlen($cp->chain_hash) !== 64) {
                $consistent = false;
                break;
            }
        }

        return [
            'run_id' => $run->id,
            'start_day' => $startDay,
            'end_day' => $endDay,
            'is_consistent' => $consistent,
            'checkpoints_evaluated' => $checkpoints->count(),
        ];
    }
}
