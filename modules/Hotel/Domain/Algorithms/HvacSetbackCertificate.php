<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

final readonly class HvacSetbackCertificate
{
    public function __construct(
        public bool $isOccupiedRoom,
        public bool $setbackActive,
        public int $setbackMinutes,
        public int $energySavedWh,
        public int $acPowerWatt,
        public int $savingFactorBps,
        public int $timestamp
    ) {}
}
