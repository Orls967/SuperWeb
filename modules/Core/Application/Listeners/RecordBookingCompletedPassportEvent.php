<?php

declare(strict_types=1);

namespace Modules\Core\Application\Listeners;

use Modules\AutoServe\Domain\Events\BookingCompleted;
use Modules\Core\Application\Actions\RecordVehicleEventAction;
use Modules\Core\Domain\Enums\VehicleEventType;

class RecordBookingCompletedPassportEvent
{
    public function __construct(
        private readonly RecordVehicleEventAction $recordEventAction
    ) {}

    public function handle(BookingCompleted $event): void
    {
        $booking = $event->booking;
        $vehicle = $booking->vehicle;

        if (! $vehicle) {
            return;
        }

        $now = now();

        // 1. Service Completed Event
        $this->recordEventAction->execute(
            vehicle: $vehicle,
            type: VehicleEventType::SERVICE_COMPLETED,
            payload: [
                'booking_code' => $booking->booking_code,
                'service_name' => $booking->service->name,
                'service_cost' => (float) $booking->service_cost,
                'total_cost' => (float) $booking->grand_total,
                'mechanic_notes' => $booking->mechanic_notes,
            ],
            actorId: $event->actorId,
            occurredAt: $now
        );

        // 2. Part Replaced Events (per sparepart)
        foreach ($booking->spareparts as $sparepart) {
            $this->recordEventAction->execute(
                vehicle: $vehicle,
                type: VehicleEventType::PART_REPLACED,
                payload: [
                    'booking_code' => $booking->booking_code,
                    'part_name' => $sparepart->name,
                    'part_code' => $sparepart->code,
                    'quantity' => (int) $sparepart->pivot->quantity,
                    'unit_price' => (float) $sparepart->pivot->price,
                    'subtotal' => (float) $sparepart->pivot->subtotal,
                ],
                actorId: $event->actorId,
                occurredAt: $now
            );
        }

        // 3. Odometer Updated Event
        if ($event->odometerKm !== null) {
            $this->recordEventAction->execute(
                vehicle: $vehicle,
                type: VehicleEventType::ODOMETER_UPDATED,
                payload: [
                    'booking_code' => $booking->booking_code,
                    'odometer_km' => (int) $event->odometerKm,
                ],
                actorId: $event->actorId,
                occurredAt: $now
            );
        }
    }
}
