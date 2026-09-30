<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Services\RoutePlanner;

use Carbon\CarbonInterface;
use Modules\Logistics\Domain\Enums\ServiceLevel;
use Modules\Logistics\Domain\Enums\TransportMode;

class RouteRequest
{
    public function __construct(
        public readonly int $originLocationId,
        public readonly int $destinationLocationId,
        public readonly ServiceLevel $serviceLevel,
        public readonly CarbonInterface $readyAt,
        public readonly float $weightKg = 1.0,
        public readonly int $volumeDm3 = 1,
        public readonly bool $isReefer = false,
        public readonly ?string $dgClass = null,
        public readonly int $maxTransfers = 3
    ) {}

    /**
     * Determine which transport modes are allowed for this service level.
     *
     * @return array<int, TransportMode>
     */
    public function allowedModes(): array
    {
        return match ($this->serviceLevel) {
            ServiceLevel::SameDay, ServiceLevel::Express => [
                TransportMode::AIR,
                TransportMode::ROAD,
            ],
            ServiceLevel::Economy, ServiceLevel::LCL, ServiceLevel::FCL => [
                TransportMode::SEA,
                TransportMode::ROAD,
            ],
            ServiceLevel::LTL, ServiceLevel::FTL, ServiceLevel::Regular => [
                TransportMode::ROAD,
            ],
            ServiceLevel::AirFreight => [
                TransportMode::AIR,
                TransportMode::ROAD, // first/last mile feeder
            ],
        };
    }
}
