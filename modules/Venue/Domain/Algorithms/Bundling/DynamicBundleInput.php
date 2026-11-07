<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms\Bundling;

final readonly class DynamicBundleInput
{
    /**
     * @param  array<int, array{name: string, floor_cost: int, base_price: int, metadata?: array}>  $components
     */
    public function __construct(
        public array $components,
        public int $venueOccupancyPercentage,
        public int $hotelOccupancyPercentage,
        public int $leadTimeDays,
    ) {}
}
