<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\DTOs;

final class DoctorExaminationDTO
{
    public function __construct(
        public string $doctorNotes,
        public string $icd10DiagnosisCode,
    ) {
    }
}
