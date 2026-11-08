<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Events;

final readonly class PointsAccruedFromEcosystemEvent
{
    public function __construct(
        public string $travelPassId,
        public string $batchId,
        public string $originatingLine,
        public int $ptsAmount,
        public int $liabilityValueIdr,
        public string $referenceCode
    ) {}
}
