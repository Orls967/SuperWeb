<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Certificates;

final class ChangeRiskCertificate
{
    public function __construct(
        public readonly string $certificateId,
        public readonly string $referenceNumber,
        public readonly float $calculatedScore,
        public readonly string $riskTier,
        public readonly array $inputFactors,
        public readonly int $timestamp,
        public readonly string $hmacSignature
    ) {}

    public function toArray(): array
    {
        return [
            'certificate_id' => $this->certificateId,
            'reference_number' => $this->referenceNumber,
            'calculated_score' => $this->calculatedScore,
            'risk_tier' => $this->riskTier,
            'input_factors' => $this->inputFactors,
            'timestamp' => $this->timestamp,
            'hmac_signature' => $this->hmacSignature,
        ];
    }
}
