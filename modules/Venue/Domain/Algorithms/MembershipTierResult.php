<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms;

final readonly class MembershipTierResult
{
    public function __construct(
        public string $resolvedTier,
        public bool $inGracePeriod,
        public bool $eligibleForBottleLocker,
        public MembershipTierCertificate $certificate,
    ) {}
}
