<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\DTOs;

final class PatientDischargeSummaryDTO
{
    /**
     * @param list<string> $secondaryDiagnoses
     * @param list<array<string, mixed>> $dischargeMedications
     * @param list<string> $followUpInstructions
     */
    public function __construct(
        public readonly int $admissionId,
        public readonly int $patientId,
        public readonly int $attendingDoctorId,
        public readonly string $primaryDiagnosisIcd10,
        public readonly string $primaryDiagnosisDescription,
        public readonly list<string> $secondaryDiagnoses,
        public readonly string $dischargeDisposition, // HOME, REFERRED, DECEASED, AGAINST_MEDICAL_ADVICE
        public readonly string $clinicalConditionAtDischarge, // CURED, IMPROVED, UNCHANGED
        public readonly list<array<string, mixed>> $dischargeMedications,
        public readonly list<string> $followUpInstructions,
        public readonly ?string $followUpDate = null,
        public readonly int $totalLengthOfStayDays = 1,
        public readonly int $totalBilledIdr = 0
    ) {}
}
