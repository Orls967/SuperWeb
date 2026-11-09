<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Care;

use Modules\Hospital\Domain\Models\HospitalBed;
use Modules\Hospital\Domain\StateMachines\HospitalBedStateMachine;

final class LockBedForMaintenanceAction
{
    /**
     * @param int $bedId
     * @param string $defectDescription
     * @param int $actorId
     * @return array<string, mixed>
     */
    public function execute(int $bedId, string $defectDescription, int $actorId): array
    {
        $bed = HospitalBed::findOrFail($bedId);

        HospitalBedStateMachine::transition(
            bed: $bed,
            toStatus: HospitalBedStateMachine::STATUS_MAINTENANCE,
            reason: "Kuncian pemeliharaan sarana medis: {$defectDescription}",
            actorId: $actorId
        );

        return [
            'success' => true,
            'bed_id' => $bed->id,
            'status' => $bed->fresh()->status,
            'reason' => $defectDescription,
        ];
    }
}
