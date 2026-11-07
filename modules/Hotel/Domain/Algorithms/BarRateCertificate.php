<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

final readonly class BarRateCertificate
{
    public function __construct(
        public int $baseRateIdr,
        public int $finalRateIdr,
        public int $floorRateIdr,
        public int $ceilingRateIdr,
        public bool $isContractOverride,
        public int $occupancyBps,
        public int $leadBps,
        public int $eventBps,
        public int $timestamp
    ) {}
}
