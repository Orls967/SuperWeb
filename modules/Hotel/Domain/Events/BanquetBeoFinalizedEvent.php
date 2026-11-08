<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Events;

final class BanquetBeoFinalizedEvent
{
    /**
     * @param  array<int, array<string, mixed>>  $bomBreakdown
     */
    public function __construct(
        public readonly string $beoOrderId,
        public readonly string $beoNumber,
        public readonly string $eventDate,
        public readonly int $totalPreparedPax,
        public readonly int $totalFoodBeverageCost,
        public readonly array $bomBreakdown = []
    ) {}
}
