<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Logistics\Domain\Enums\DeliveryFailureReason;
use Modules\Logistics\Domain\Enums\ExceptionSeverity;
use Modules\Logistics\Domain\Enums\ExceptionType;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Exceptions\InvalidDeliveryOperationException;
use Modules\Logistics\Domain\Models\DeliveryAttempt;
use Modules\Logistics\Domain\Models\Driver;
use Modules\Logistics\Domain\Models\Shipment;

class ReportFailedDeliveryAction extends AbstractDriverTaskAction
{
    public const MAX_ATTEMPTS = 3;

    public function __construct(
        protected RecordTrackingEventAction $recordEvent,
        protected RaiseShipmentExceptionAction $raiseException
    ) {}

    /**
     * Catat percobaan pengantaran gagal. Pada kegagalan ke-3 resi otomatis menjadi ReturnToSender.
     *
     * @return array{shipment: Shipment, attempt: DeliveryAttempt, returned: bool}
     */
    public function execute(Driver $driver, Shipment $shipment, DeliveryFailureReason $reason, ?string $notes = null): array
    {
        return DB::transaction(function () use ($driver, $shipment, $reason, $notes) {
            $shipment = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            $this->assertAssignedTo($shipment, $driver);

            if ($shipment->status !== ShipmentStatus::OutForDelivery) {
                throw new InvalidDeliveryOperationException("Resi {$shipment->tracking_number} berstatus '{$shipment->status->label()}' dan tidak sedang dalam pengantaran.");
            }

            $attemptNumber = $shipment->failed_delivery_attempts + 1;

            $attempt = DeliveryAttempt::create([
                'shipment_id' => $shipment->id,
                'driver_id' => $driver->id,
                'attempt_number' => $attemptNumber,
                'outcome' => 'failed',
                'reason_code' => $reason->value,
                'notes' => $notes,
                'attempted_at' => now(),
            ]);

            $shipment->failed_delivery_attempts = $attemptNumber;
            $shipment->save();

            $this->recordEvent->execute(
                shipment: $shipment,
                eventType: 'DELIVERY_FAILED',
                locationId: $shipment->destination_location_id,
                actor: $driver->user,
                actorRole: 'driver',
                description: "Pengantaran gagal (percobaan ke-{$attemptNumber}): {$reason->label()}.",
                payload: ['driver_id' => $driver->id, 'attempt' => $attemptNumber, 'reason' => $reason->value]
            );

            $returned = $attemptNumber >= self::MAX_ATTEMPTS;

            $this->raiseException->execute(
                shipment: $shipment,
                type: ExceptionType::DeliveryFailed,
                description: "Pengantaran gagal (percobaan ke-{$attemptNumber}): {$reason->label()}.",
                reporter: $driver->user,
                severity: $returned ? ExceptionSeverity::High : null,
                locationId: $shipment->destination_location_id,
                dedupeKey: "delivery_failed:{$shipment->id}:{$attemptNumber}",
                payload: ['attempt' => $attemptNumber, 'reason' => $reason->value, 'driver_id' => $driver->id],
            );

            if ($returned) {
                $shipment->delivery_otp_hash = null;
                $shipment->transitionTo(ShipmentStatus::ReturnToSender);

                $this->recordEvent->execute(
                    shipment: $shipment,
                    eventType: 'RETURN_TO_SENDER',
                    locationId: $shipment->destination_location_id,
                    actor: $driver->user,
                    actorRole: 'driver',
                    description: 'Pengantaran gagal 3 kali; kargo dikembalikan ke pengirim.',
                    payload: ['driver_id' => $driver->id, 'attempts' => $attemptNumber]
                );
            }

            return ['shipment' => $shipment, 'attempt' => $attempt, 'returned' => $returned];
        });
    }
}
