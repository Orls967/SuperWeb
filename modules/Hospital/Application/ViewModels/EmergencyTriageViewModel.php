<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\ViewModels;

use Modules\Hospital\Domain\Models\Admission;
use Modules\Hospital\Domain\Models\HospitalBed;
use Modules\Hospital\Domain\Models\Patient;

final class EmergencyTriageViewModel
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $activeTriage = Admission::where('status', 'TRIAGE')
            ->orWhere(fn ($q) => $q->where('admission_type', 'EMERGENCY')->whereIn('status', ['ADMITTED', 'BED_RESERVED']))
            ->with(['patient', 'bed'])
            ->get();

        $availableErBeds = HospitalBed::where('department', 'IGD')
            ->where('status', 'AVAILABLE')
            ->count();

        return [
            'available_er_beds' => $availableErBeds,
            'active_triage_count' => $activeTriage->count(),
            'triage_list' => $activeTriage->map(fn ($adm) => [
                'admission_id' => $adm->id,
                'patient_name' => $adm->patient?->name,
                'mrn' => $adm->patient?->mrn,
                'acuity' => $adm->metadata['acuity_level'] ?? 'STABLE',
                'admission_time' => $adm->admission_time,
                'assigned_bed' => $adm->bed?->bed_code ?? 'WAITING_ALLOCATION',
            ])->toArray(),
        ];
    }
}
