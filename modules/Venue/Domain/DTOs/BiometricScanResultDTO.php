<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\DTOs;

final readonly class BiometricScanResultDTO
{
    public function __construct(
        public int $userId,
        public string $gateCode,
        public bool $accessGranted,
        public int $livenessScoreBps,
        public string $tamperProofAuditToken,
        public int $scanTimestamp,
        public ?string $denialReason = null
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'gate_code' => $this->gateCode,
            'access_granted' => $this->accessGranted,
            'liveness_score_bps' => $this->livenessScoreBps,
            'audit_token' => $this->tamperProofAuditToken,
            'scan_timestamp' => $this->scanTimestamp,
            'denial_reason' => $this->denialReason,
        ];
    }
}
