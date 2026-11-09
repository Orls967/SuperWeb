<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\DTOs;

final class PatientDischargeSummaryDTO
{
    /**
     * @param  list<string>  $secondaryDiagnoses
     * @param  list<array<string, mixed>>  $dischargeMedications
     * @param  list<string>  $followUpInstructions
     */
    public function __construct(
        public readonly int $admissionId,
        public readonly int $patientId,
        public readonly int $attendingDoctorId,
        public readonly string $primaryDiagnosisIcd10,
        public readonly string $primaryDiagnosisDescription,
        /** @var list<string> */
        public readonly array $secondaryDiagnoses,
        public readonly string $dischargeDisposition, // HOME, REFERRED, DECEASED, AGAINST_MEDICAL_ADVICE
        public readonly string $clinicalConditionAtDischarge, // CURED, IMPROVED, UNCHANGED
        /** @var list<array<string, mixed>> */
        public readonly array $dischargeMedications,
        /** @var list<string> */
        public readonly array $followUpInstructions,
        public readonly ?string $followUpDate = null,
        public readonly int $totalLengthOfStayDays = 1,
        public readonly int $totalBilledIdr = 0
    ) {}
}
