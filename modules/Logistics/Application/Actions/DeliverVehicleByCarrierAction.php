<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Actions\RecordVehicleEventAction;
use Modules\Core\Domain\Enums\VehicleEventType;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Core\Domain\Models\VehicleEvent;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Events\ShipmentDelivered;
use Modules\Logistics\Domain\Models\Shipment;

/**
 * Handles delivery of a vehicle via car carrier (FTL) shipment.
 *
 * Updates shipment status to Delivered and appends a DELIVERED_BY_CARRIER
 * event to the Vehicle Passport's cryptographic hash chain.
 */
class DeliverVehicleByCarrierAction
{
    public function __construct(
        private readonly RecordVehicleEventAction $recordVehicleEvent,
    ) {}

    public function execute(Shipment $shipment, Vehicle $vehicle, User $actor): VehicleEvent
    {
        return DB::transaction(function () use ($shipment, $vehicle, $actor) {
            $shipment->status = ShipmentStatus::Delivered;
            $shipment->delivered_at = now();
            $shipment->save();

            // Append to vehicle passport hash chain
            $event = $this->recordVehicleEvent->execute(
                vehicle: $vehicle,
                type: VehicleEventType::DELIVERED_BY_CARRIER,
                payload: [
                    'tracking_number' => $shipment->tracking_number,
                    'origin_location' => $shipment->originLocation?->code ?? 'HUB',
                    'destination_location' => $shipment->destinationLocation?->code ?? 'DEST',
                    'total_amount_idr' => $shipment->total_amount_idr,
                    'delivered_by' => $actor->name,
                ],
                actorId: $actor->id,
            );

            event(new ShipmentDelivered($shipment->fresh()));

            return $event;
        });
    }
}
