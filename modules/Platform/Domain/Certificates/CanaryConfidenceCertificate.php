<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Certificates;

final class CanaryConfidenceCertificate
{
    public function __construct(
        public readonly string $certificateId,
        public readonly int $deploymentId,
        public readonly float $baselineRate,
        public readonly float $canaryRate,
        public readonly float $zScore,
        public readonly string $recommendation,
        public readonly int $sampleCount,
        public readonly int $timestamp,
        public readonly string $rsaShaSignature
    ) {}

    public function isVerifiedHealthy(): bool
    {
        return $this->recommendation !== 'rollback' && $this->zScore < 3.0;
    }
}
