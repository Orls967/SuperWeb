<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms;

final readonly class SponsorExposureResult
{
    public function __construct(
        public int $deliveredImpressions,
        public int $achievementBps,
        public int $payoutRatioBps,
        public int $exposureValueIdr,
        public int $milestonePayoutIdr,
        public SponsorExposureCertificate $certificate,
    ) {}
}
