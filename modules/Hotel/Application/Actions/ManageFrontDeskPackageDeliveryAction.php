<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Modules\Hotel\Domain\Models\HotelReservation;

/**
 * Tindakan Penerimaan & Penyerahan Paket Kiriman Tamu In-House (Guest Parcel Delivery).
 */
final class ManageFrontDeskPackageDeliveryAction
{
    /**
     * @return array{parcel_tracking_code: string, recipient_name: string, room_number: string, status: string}
     */
    public function receiveParcel(
        string|int $reservationId,
        string $courierName,
        string $airwayBillNumber,
        string $itemDescription
    ): array {
        $reservation = HotelReservation::findOrFail((string) $reservationId);
        $tracking = 'PRC-' . date('md') . '-' . substr(md5($airwayBillNumber . $reservationId), 0, 5);

        return [
            'parcel_tracking_code' => strtoupper($tracking),
            'recipient_name' => (string) ($reservation->guest_name ?: 'Tamu In-House'),
            'room_number' => (string) ($reservation->room_number ?: 'FO-DESK'),
            'status' => 'STORED_AT_CONCIERGE',
        ];
    }

    /**
     * @return array{parcel_tracking_code: string, handed_over: bool}
     */
    public function handoverParcelToGuest(string $parcelTrackingCode, string $receiverSignatureName): array
    {
        return [
            'parcel_tracking_code' => $parcelTrackingCode,
            'handed_over' => true,
        ];
    }
}
