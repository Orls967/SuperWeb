<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Services\RoutePlanner;

use Carbon\CarbonInterface;

class RouteItinerary
{
    /**
     * @param  array<int, RouteEdge>  $legs
     */
    public function __construct(
        public readonly array $legs,
        public readonly CarbonInterface $departureTime,
        public readonly CarbonInterface $arrivalTime,
        public readonly int $totalDurationMinutes,
        public readonly int $totalCostIdr = 0
    ) {}

    public function transferCount(): int
    {
        return max(0, count($this->legs) - 1);
    }

    public function legCount(): int
    {
        return count($this->legs);
    }
}
