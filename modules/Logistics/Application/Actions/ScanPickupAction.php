<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Exceptions\InvalidDeliveryOperationException;
use Modules\Logistics\Domain\Models\Driver;
use Modules\Logistics\Domain\Models\Shipment;

class ScanPickupAction extends AbstractDriverTaskAction
{
    public function __construct(
        protected RecordTrackingEventAction $recordEvent
    ) {}

    /**
     * Pengemudi memindai resi saat menjemput paket dari pengirim (Booked -> PickedUp).
     */
    public function execute(Driver $driver, Shipment $shipment): Shipment
    {
        return DB::transaction(function () use ($driver, $shipment) {
            $shipment = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            $this->assertAssignedTo($shipment, $driver);

            if ($shipment->status !== ShipmentStatus::Booked) {
                throw new InvalidDeliveryOperationException("Resi {$shipment->tracking_number} berstatus '{$shipment->status->label()}' dan tidak dapat dipindai pickup.");
            }

            $shipment->transitionTo(ShipmentStatus::PickedUp);

            $this->recordEvent->execute(
                shipment: $shipment,
                eventType: 'PICKED_UP',
                locationId: $shipment->origin_location_id,
                actor: $driver->user,
                actorRole: 'driver',
                description: 'Kargo dijemput oleh kurir dari pengirim.',
                payload: ['driver_id' => $driver->id, 'driver_number' => $driver->driver_number]
            );

            return $shipment;
        });
    }
}
