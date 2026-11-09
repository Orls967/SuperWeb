<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Care;

use Modules\Hospital\Domain\Models\Admission;
use Modules\Hospital\Domain\StateMachines\AdmissionStateMachine;

/**
 * Aksi Perencanaan Pemulangan Pasien Rawat Inap oleh Dokter DPJP (Fase 87.3).
 */
class PlanDischargeAction
{
    public function execute(int $admissionId, string $medicalSummary, ?int $actorId = null): Admission
    {
        $admission = Admission::findOrFail($admissionId);
        AdmissionStateMachine::transition(
            $admission,
            AdmissionStateMachine::STATUS_DISCHARGE_PLANNED,
            "Resume medis DPJP: {$medicalSummary}",
            $actorId,
            ['summary' => $medicalSummary]
        );

        return $admission->fresh();
    }
}
