<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Listeners;

use Modules\Logistics\Contracts\FleetMaintenanceBooking;
use Modules\Logistics\Domain\Enums\FleetStatus;
use Modules\Logistics\Domain\Events\FleetServiceDue;
use Modules\Logistics\Domain\Models\Truck;

class HandleFleetServiceDue
{
    public function __construct(
        private readonly FleetMaintenanceBooking $fleetBooking,
    ) {}

    public function handle(FleetServiceDue $event): void
    {
        $truck = Truck::find($event->truckId);

        if (! $truck) {
            return;
        }

        // Set fleet status to MAINTENANCE (blocks schedule assignment)
        $truck->status = FleetStatus::MAINTENANCE;
        $truck->save();

        // Create booking in AutoServe via contract
        $this->fleetBooking->bookFleetService(
            $event->vehicleId,
            $event->odometerKm,
            "Perawatan armada {$truck->plate_number}"
        );
    }
}
