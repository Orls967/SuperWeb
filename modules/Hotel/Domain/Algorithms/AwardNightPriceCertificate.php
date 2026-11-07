<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

final readonly class AwardNightPriceCertificate
{
    public function __construct(
        public int $finalPointsRequired,
        public int $reimbursementValueIdr,
        public int $floorGuaranteedIdr,
        public bool $isFloorApplied,
        public int $tierDiscountPts,
        public string $signatureHash
    ) {}
}
