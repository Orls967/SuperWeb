<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\ViewModels;

use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Services\PatientPassportService;

final class PatientPassportViewModel
{
    private PatientPassportService $passportService;

    public function __construct(PatientPassportService $passportService)
    {
        $this->passportService = $passportService;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(int $patientId): array
    {
        $patient = Patient::findOrFail($patientId);
        $timeline = $this->passportService->getChronologicalTimeline($patientId);
        $isTampered = $this->passportService->detectTampering($patientId);

        return [
            'patient' => [
                'id' => $patient->id,
                'mrn' => $patient->mrn,
                'name' => $patient->name,
                'nik' => $patient->nik,
                'birth_date' => $patient->birth_date,
                'blood_type' => $patient->blood_type,
            ],
            'timeline_events_count' => count($timeline),
            'timeline' => $timeline,
            'is_tamper_detected' => $isTampered,
            'integrity_status' => $isTampered ? 'INTEGRITY_COMPROMISED' : 'VERIFIED_CRYPTOGRAPHIC_CHAIN',
        ];
    }
}
