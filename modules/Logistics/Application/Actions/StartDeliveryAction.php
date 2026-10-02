<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Application\Services\NotificationService;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Exceptions\InvalidDeliveryOperationException;
use Modules\Logistics\Domain\Models\Driver;
use Modules\Logistics\Domain\Models\Shipment;

class StartDeliveryAction extends AbstractDriverTaskAction
{
    public function __construct(
        protected RecordTrackingEventAction $recordEvent,
        protected NotificationService $notifications
    ) {}

    /**
     * Mulai pengantaran last-mile (AtHub -> OutForDelivery). Membuat OTP 6 digit yang hanya
     * disimpan sebagai hash; OTP asli dikirim ke pengirim untuk diteruskan ke penerima.
     * Pemanggilan ulang saat status OutForDelivery menerbitkan OTP baru (kirim ulang).
     *
     * @return array{shipment: Shipment, otp: string}
     */
    public function execute(Driver $driver, Shipment $shipment): array
    {
        return DB::transaction(function () use ($driver, $shipment) {
            $shipment = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            $this->assertAssignedTo($shipment, $driver);

            $isResend = $shipment->status === ShipmentStatus::OutForDelivery;

            if (! $isResend && $shipment->status !== ShipmentStatus::AtHub) {
                throw new InvalidDeliveryOperationException("Resi {$shipment->tracking_number} berstatus '{$shipment->status->label()}'; pengantaran hanya dapat dimulai dari status Tiba di Hub.");
            }

            $otp = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
            $shipment->delivery_otp_hash = Hash::make($otp);
            $shipment->save();

            if (! $isResend) {
                $shipment->transitionTo(ShipmentStatus::OutForDelivery);

                $this->recordEvent->execute(
                    shipment: $shipment,
                    eventType: 'OUT_FOR_DELIVERY',
                    locationId: $shipment->destination_location_id,
                    actor: $driver->user,
                    actorRole: 'driver',
                    description: 'Kargo dibawa kurir menuju alamat penerima.',
                    payload: ['driver_id' => $driver->id, 'driver_number' => $driver->driver_number]
                );
            }

            $this->notifications->send(
                userId: $shipment->shipper_id,
                type: 'logistics.delivery_otp',
                title: "OTP pengantaran {$shipment->tracking_number}",
                body: "Kode OTP penerima: {$otp}. Berikan kode ini kepada penerima; kurir akan memintanya saat serah terima.",
                icon: 'truck',
                meta: ['shipment_id' => $shipment->id, 'resend' => $isResend],
            );

            return ['shipment' => $shipment, 'otp' => $otp];
        });
    }
}
