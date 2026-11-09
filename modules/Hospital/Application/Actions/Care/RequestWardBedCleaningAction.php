<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Care;

use Modules\Hospital\Domain\Models\HospitalBed;
use Modules\Hospital\Domain\StateMachines\HospitalBedStateMachine;

final class RequestWardBedCleaningAction
{
    /**
     * @param  string  $priority  // ROUTINE, STAT, TERMINAL_DISINFECTION
     * @return array<string, mixed>
     */
    public function execute(int $bedId, string $priority, int $actorId): array
    {
        $bed = HospitalBed::findOrFail($bedId);

        HospitalBedStateMachine::transition(
            bed: $bed,
            toStatus: HospitalBedStateMachine::STATUS_CLEANING,
            reason: "Permintaan sanitasi kamar rawat inap prioritas: {$priority}",
            actorId: $actorId
        );

        return [
            'success' => true,
            'bed_id' => $bed->id,
            'status' => $bed->fresh()->status,
            'priority' => $priority,
        ];
    }
}
