<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms;

final readonly class MembershipTierCertificate
{
    public function __construct(
        public string $currentTier,
        public int $spend365DaysIdr,
        public int $visits365Days,
        public bool $isAnniversaryEvaluation,
        public string $resolvedTier,
        public bool $inGracePeriod,
        public bool $eligibleForBottleLocker,
        public string $hmacSignature,
    ) {}

    public function isValid(): bool
    {
        return MembershipTierVerifier::verify($this);
    }
}
