<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms;

final readonly class SponsorExposureCertificate
{
    public function __construct(
        public int $deliveredImpressions,
        public int $targetImpressions,
        public int $achievementBps,
        public int $payoutRatioBps,
        public int $exposureValueIdr,
        public int $milestonePayoutIdr,
        public string $hmacSignature,
    ) {}

    public function isValid(): bool
    {
        return SponsorExposureVerifier::verify($this);
    }
}
