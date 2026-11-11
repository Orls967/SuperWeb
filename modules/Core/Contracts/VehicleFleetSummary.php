<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Value Object summarizing fleet metrics across the ecosystem.
 */
final class VehicleFleetSummary
{
    /**
     * @param  array<string, int>  $statusDistribution
     */
    public function __construct(
        public readonly int $totalVehicles,
        public readonly int $activeVehicles,
        public readonly int $maintenanceVehicles,
        public readonly int $inactiveVehicles,
        public readonly int $averageOdometerKm,
        public readonly array $statusDistribution,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total_vehicles' => $this->totalVehicles,
            'active_vehicles' => $this->activeVehicles,
            'maintenance_vehicles' => $this->maintenanceVehicles,
            'inactive_vehicles' => $this->inactiveVehicles,
            'average_odometer_km' => $this->averageOdometerKm,
            'status_distribution' => $this->statusDistribution,
        ];
    }
}

