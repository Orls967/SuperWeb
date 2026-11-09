<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Diagnostics;

use Modules\Hospital\Application\Services\DiagnosticImagingPacsIntegrationService;
use Modules\Hospital\Domain\DTO\DicomStudyUploadDTO;
use Modules\Hospital\Domain\Models\ImagingStudy;
use Modules\Hospital\Domain\Models\Patient;
use RuntimeException;

class UploadDicomStudyAction
{
    public function __construct(
        protected DiagnosticImagingPacsIntegrationService $pacsService
    ) {}

    public function execute(DicomStudyUploadDTO $dto): ImagingStudy
    {
        /** @var Patient|null $patient */
        $patient = Patient::find($dto->patientId);

        if (! $patient) {
            throw new RuntimeException("Pasien dengan ID [{$dto->patientId}] tidak ditemukan.");
        }

        return $this->pacsService->recordImagingStudy(
            patient: $patient,
            modality: $dto->modality,
            bodyPart: $dto->bodyPart,
            turnaroundMinutes: $dto->turnaroundMinutes,
            feeIdr: $dto->feeIdr,
            findings: $dto->findings,
            priority: $dto->priority,
            studyUid: $dto->studyInstanceUid
        );
    }
}
