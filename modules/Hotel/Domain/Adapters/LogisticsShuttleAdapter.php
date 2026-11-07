<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Adapters;

use Carbon\CarbonImmutable;

/**
 * Adaptor Integrasi Logistics Shipment/Shuttle Booking untuk Relokasi Overbooking Tamu
 */
final class LogisticsShuttleAdapter
{
    /**
     * @return array{booking_id: string, carrier_service: string, pickup_time: string, status: string}
     */
    public static function bookGuestRelocationShuttle(
        int $originPropertyId,
        int $targetPropertyId,
        int $guestUserId
    ): array {
        $bookingId = "LGX-SHTL-" . time() . "-{$guestUserId}";

        return [
            'booking_id' => $bookingId,
            'carrier_service' => 'HOTEL_VIP_TRANSFER',
            'pickup_time' => CarbonImmutable::now()->addMinutes(15)->toIso8601String(),
            'status' => 'CONFIRMED',
        ];
    }
}
