<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

final readonly class GlobalTierQualificationCertificate
{
    public function __construct(
        public string $qualifiedTier,
        public bool $isUpgraded,
        public bool $isDowngraded,
        public bool $isGracePeriodActive,
        public float $progressToNextTierPercentage,
        public string $signatureHash
    ) {}
}
