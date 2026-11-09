<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Care;

use Modules\Hospital\Domain\Models\HospitalBed;
use Modules\Hospital\Domain\StateMachines\HospitalBedStateMachine;

final class ReleaseBedFromMaintenanceAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(int $bedId, string $serviceReportRef, int $actorId): array
    {
        $bed = HospitalBed::findOrFail($bedId);

        HospitalBedStateMachine::transition(
            bed: $bed,
            toStatus: HospitalBedStateMachine::STATUS_AVAILABLE,
            reason: "Pemeliharaan & kalibrasi alkes tuntas. Laporan teknis ref: {$serviceReportRef}",
            actorId: $actorId
        );

        return [
            'success' => true,
            'bed_id' => $bed->id,
            'status' => $bed->fresh()->status,
            'service_report_ref' => $serviceReportRef,
        ];
    }
}
