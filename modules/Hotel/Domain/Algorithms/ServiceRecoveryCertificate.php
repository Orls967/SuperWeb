<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

final readonly class ServiceRecoveryCertificate
{
    public function __construct(
        public int $nightRateIdr,
        public float $excessHours,
        public int $compensationAmountIdr,
        public int $compensationPercentBps,
        public bool $grantsFreeNight,
        public int $timestamp
    ) {}
}
