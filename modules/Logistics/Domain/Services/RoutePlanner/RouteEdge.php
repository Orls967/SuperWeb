<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Services\RoutePlanner;

use Carbon\CarbonInterface;
use Modules\Logistics\Domain\Enums\TransportMode;

class RouteEdge
{
    /**
     * @param  array<int, string>  $prohibitedDgClasses
     */
    public function __construct(
        public readonly ?int $scheduleId,
        public readonly string $scheduleNumber,
        public readonly int $originLocationId,
        public readonly int $destinationLocationId,
        public readonly TransportMode $mode,
        public readonly CarbonInterface $etd,
        public readonly CarbonInterface $eta,
        public readonly CarbonInterface $cutoffAt,
        public readonly int $costIdr = 0,
        public readonly bool $isReeferCapable = false,
        public readonly array $prohibitedDgClasses = [],
        public readonly float $availableWeightKg = 999999.0,
        public readonly int $availableVolumeDm3 = 999999,
        public readonly int $availableTeu = 999,
        public readonly int $availableUldPositions = 999
    ) {}

    public function durationMinutes(): int
    {
        return (int) $this->etd->diffInMinutes($this->eta);
    }

    public function allowsDgClass(?string $dgClass): bool
    {
        if ($dgClass === null || trim($dgClass) === '') {
            return true;
        }

        // Air freight strictly prohibits DG Class 1 (Explosives) and Class 7 (Radioactive)
        if ($this->mode === TransportMode::AIR && in_array($dgClass, ['1', '1.1', '1.2', '1.3', '1.4', '7'], true)) {
            return false;
        }

        return ! in_array($dgClass, $this->prohibitedDgClasses, true);
    }
}
