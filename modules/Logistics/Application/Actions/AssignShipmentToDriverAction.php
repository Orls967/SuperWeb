<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Exceptions\DriverLicenseExpiredException;
use Modules\Logistics\Domain\Exceptions\DriverNotAssignableException;
use Modules\Logistics\Domain\Exceptions\InvalidDispatchException;
use Modules\Logistics\Domain\Models\Driver;
use Modules\Logistics\Domain\Models\Shipment;

class AssignShipmentToDriverAction
{
    /** Status resi yang masih memerlukan stop pickup (Booked) atau pengantaran last-mile (AtHub/OutForDelivery). */
    public const ASSIGNABLE_STATUSES = [
        ShipmentStatus::Booked,
        ShipmentStatus::AtHub,
        ShipmentStatus::OutForDelivery,
    ];

    public function __construct(
        protected RecordTrackingEventAction $recordEvent
    ) {}

    /**
     * Tugaskan resi ke pengemudi untuk pickup (Booked) atau pengantaran last-mile (AtHub).
     */
    public function execute(Shipment $shipment, Driver $driver, User $dispatcher): Shipment
    {
        return DB::transaction(function () use ($shipment, $driver, $dispatcher) {
            $shipment = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            $driver = Driver::whereKey($driver->id)->firstOrFail();

            if (! in_array($shipment->status, self::ASSIGNABLE_STATUSES, true)) {
                throw new InvalidDispatchException("Resi {$shipment->tracking_number} berstatus '{$shipment->status->label()}' dan tidak dapat ditugaskan ke pengemudi.");
            }

            if ($driver->status === 'suspended') {
                throw DriverNotAssignableException::forDriver($driver->driver_number, 'pengemudi sedang dinonaktifkan (suspended).');
            }

            if (! $driver->isLicenseValid()) {
                throw new DriverLicenseExpiredException("SIM pengemudi {$driver->driver_number} telah kedaluwarsa pada {$driver->license_expiry->format('d/m/Y')}.");
            }

            $previousDriverId = $shipment->driver_id;
            $shipment->driver_id = $driver->id;
            $shipment->save();

            $this->recordEvent->execute(
                shipment: $shipment,
                eventType: 'DRIVER_ASSIGNED',
                actor: $dispatcher,
                actorRole: 'dispatcher',
                description: $shipment->status === ShipmentStatus::Booked
                    ? 'Kurir ditugaskan untuk menjemput kargo.'
                    : 'Kurir ditugaskan untuk pengantaran last-mile.',
                payload: [
                    'driver_id' => $driver->id,
                    'driver_number' => $driver->driver_number,
                    'previous_driver_id' => $previousDriverId,
                    'stage' => $shipment->status === ShipmentStatus::Booked ? 'pickup' : 'delivery',
                ]
            );

            return $shipment;
        });
    }
}
