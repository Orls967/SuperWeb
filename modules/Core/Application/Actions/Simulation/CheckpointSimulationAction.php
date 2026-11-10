<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\Simulation;

use Modules\Core\Domain\Models\SimCheckpoint;
use Modules\Core\Domain\Models\SimRun;

final class CheckpointSimulationAction
{
    /**
     * @param  array<string, mixed>  $handlerHashes
     * @param  array<string, mixed>  $stateSummary
     */
    public function execute(
        SimRun $run,
        int $dayNumber,
        string $chainHash,
        array $handlerHashes = [],
        array $stateSummary = [],
    ): SimCheckpoint {
        return SimCheckpoint::updateOrCreate(
            [
                'run_id' => $run->id,
                'day_number' => $dayNumber,
            ],
            [
                'chain_hash' => $chainHash,
                'handler_hashes' => $handlerHashes,
                'state_summary' => $stateSummary,
            ]
        );
    }
}
