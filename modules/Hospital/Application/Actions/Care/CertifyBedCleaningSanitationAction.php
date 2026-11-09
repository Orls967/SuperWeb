<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Care;

use Modules\Hospital\Domain\Models\HospitalBed;
use Modules\Hospital\Domain\StateMachines\HospitalBedStateMachine;

final class CertifyBedCleaningSanitationAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(int $bedId, string $sanitizerStaffName, int $actorId): array
    {
        $bed = HospitalBed::findOrFail($bedId);

        HospitalBedStateMachine::transition(
            bed: $bed,
            toStatus: HospitalBedStateMachine::STATUS_AVAILABLE,
            reason: "Sertifikasi sanitasi housekeeping selesai oleh {$sanitizerStaffName}",
            actorId: $actorId
        );

        return [
            'success' => true,
            'bed_id' => $bed->id,
            'status' => $bed->fresh()->status,
            'certified_by' => $sanitizerStaffName,
        ];
    }
}
