<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\DTOs;

final class BiometricComplianceCertificateDTO
{
    public function __construct(
        public readonly string $certificateId,
        public readonly int $subjectUserId,
        public readonly string $regulatoryStandard,
        public readonly string $actionTaken,
        public readonly string $erasureTimestamp,
        public readonly string $cryptographicSeal,
        public readonly bool $isVerifiablyScrubbed
    ) {}

    public function toArray(): array
    {
        return [
            'certificate_id' => $this->certificateId,
            'subject_user_id' => $this->subjectUserId,
            'regulatory_standard' => $this->regulatoryStandard,
            'action_taken' => $this->actionTaken,
            'erasure_timestamp' => $this->erasureTimestamp,
            'cryptographic_seal' => $this->cryptographicSeal,
            'is_verifiably_scrubbed' => $this->isVerifiablyScrubbed,
        ];
    }
}
