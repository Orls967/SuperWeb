<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\ViewModels;

use Modules\Hospital\Domain\Models\Admission;
use Modules\Hospital\Domain\Models\HospitalBed;

final class NursingStationViewModel
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $admissions = Admission::whereIn('status', ['ADMITTED', 'BED_OCCUPIED', 'ACTIVE'])
            ->with(['patient', 'bed'])
            ->get();

        return [
            'total_inpatient_monitored' => $admissions->count(),
            'admissions' => $admissions->map(fn ($adm) => [
                'admission_id' => $adm->id,
                'patient_id' => $adm->patient_id,
                'patient_name' => $adm->patient?->name,
                'mrn' => $adm->patient?->mrn,
                'bed_code' => $adm->bed?->bed_code,
                'room' => $adm->bed?->room_number,
                'ward' => $adm->bed?->department,
                'diet' => $adm->metadata['clinical_diet']['diet_type'] ?? 'REGULAR',
                'admission_time' => $adm->admission_time,
            ])->toArray(),
        ];
    }
}
