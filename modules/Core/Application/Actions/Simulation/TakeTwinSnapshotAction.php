<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\Simulation;

use Modules\Core\Domain\Models\SimTwinSnapshot;
use Modules\Core\Domain\ValueObjects\Simulation\TwinSnapshotState;

final class TakeTwinSnapshotAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(string $type, string $id, int $day, array $payload, ?string $runId = null): SimTwinSnapshot
    {
        $snapshotState = TwinSnapshotState::create($type, $id, $day, $payload);

        return SimTwinSnapshot::updateOrCreate(
            [
                'run_id' => $runId,
                'day_number' => $day,
                'entity_type' => $type,
                'entity_id' => $id,
            ],
            [
                'state_hash' => $snapshotState->stateHash,
                'state_payload' => $payload,
            ]
        );
    }
}
