<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Events;

final readonly class PointsBreakageRecognizedEvent
{
    public function __construct(
        public int $totalExpiredPts,
        public int $breakageRevenueIdr,
        public int $batchesAffectedCount
    ) {}
}
