<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\DTO;

readonly class DicomStudyUploadDTO
{
    public function __construct(
        public int $patientId,
        public string $modality,
        public string $bodyPart,
        public int $turnaroundMinutes,
        public int $feeIdr,
        public ?string $findings = null,
        public string $priority = 'ROUTINE',
        public ?string $studyInstanceUid = null
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            patientId: (int) ($data['patient_id'] ?? 0),
            modality: (string) ($data['modality'] ?? 'CT'),
            bodyPart: (string) ($data['body_part'] ?? 'HEAD_BRAIN'),
            turnaroundMinutes: (int) ($data['turnaround_minutes'] ?? 0),
            feeIdr: (int) ($data['fee_idr'] ?? 0),
            findings: isset($data['radiologist_findings']) ? (string) $data['radiologist_findings'] : null,
            priority: (string) ($data['priority'] ?? 'ROUTINE'),
            studyInstanceUid: isset($data['study_instance_uid']) ? (string) $data['study_instance_uid'] : null,
        );
    }
}
