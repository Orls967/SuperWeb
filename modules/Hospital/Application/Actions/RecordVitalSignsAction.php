<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Modules\Hospital\Application\DTOs\VitalSignsDTO;
use Modules\Hospital\Domain\Models\OutpatientEncounter;

class RecordVitalSignsAction
{
    public function execute(OutpatientEncounter $encounter, VitalSignsDTO $dto, ?User $actor = null): OutpatientEncounter
    {
        if ($actor !== null && ! in_array($actor->role, ['nurse', 'admin', 'doctor'], true)) {
            throw new AuthorizationException('Hanya perawat dan staf medis berwenang mencatat tanda vital.');
        }

        $encounter->update([
            'systolic_bp' => $dto->systolic,
            'diastolic_bp' => $dto->diastolic,
            'pulse_rate' => $dto->pulse,
            'respiratory_rate' => $dto->respiratory,
            'body_temp_c' => $dto->temperature,
        ]);

        return $encounter->fresh();
    }
}
