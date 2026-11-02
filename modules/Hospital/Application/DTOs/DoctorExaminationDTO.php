<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\DTOs;

final class DoctorExaminationDTO
{
    public function __construct(
        public string $doctorNotes,
        public string $icd10DiagnosisCode,
    ) {
        if (trim($this->icd10DiagnosisCode) === '') {
            throw new \InvalidArgumentException('ICD-10 code cannot be empty.');
        }
    }
}
