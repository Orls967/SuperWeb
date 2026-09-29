<?php

declare(strict_types=1);

namespace Modules\Core\Application\Listeners;

use Modules\Core\Application\Actions\RecordVehicleEventAction;
use Modules\Core\Domain\Enums\VehicleEventType;
use Modules\Core\Domain\Events\VehicleAcquired;

class RecordVehicleAcquiredPassportEvent
{
    public function __construct(
        private readonly RecordVehicleEventAction $recordEventAction
    ) {}

    public function handle(VehicleAcquired $event): void
    {
        $vehicle = $event->vehicle;

        $this->recordEventAction->execute(
            vehicle: $vehicle,
            type: VehicleEventType::ACQUIRED,
            payload: [
                'method' => $event->method,
                'plate_number' => $vehicle->plate_number,
                'vin' => $vehicle->vin,
                'color' => $vehicle->color,
                'odometer_km' => (int) $vehicle->odometer_km,
                'car_model' => $vehicle->car ? "{$vehicle->car->brand->name} {$vehicle->car->model}" : null,
            ],
            actorId: $event->actorId,
            occurredAt: $vehicle->acquired_at ?? now()
        );
    }
}
