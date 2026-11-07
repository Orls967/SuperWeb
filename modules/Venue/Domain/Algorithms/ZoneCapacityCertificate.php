<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms;

final readonly class ZoneCapacityCertificate
{
    public function __construct(
        public int $legalCapacity,
        public int $safetyMarginBps,
        public int $effectiveCapacity,
        public int $ratePerMinute,
        public int $occupancyBefore,
        public int $waitingPatrons,
        public int $admittedPatrons,
        public int $rejectedPatrons,
        public int $occupancyAfter,
        public string $hmacSignature,
    ) {}
}

