<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Math;

/**
 * Value object representing consumption across a single meter reading interval.
 */
final readonly class MeterInterval
{
    public function __construct(
        public int $startTimestamp,
        public int $endTimestamp,
        public int $startRegister,
        public int $endRegister,
        public int $consumption,
        public bool $isRollover,
        public bool $isReset,
        public bool $isAnomaly,
    ) {}
}
