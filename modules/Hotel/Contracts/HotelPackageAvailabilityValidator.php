<?php

declare(strict_types=1);

namespace Modules\Hotel\Contracts;

interface HotelPackageAvailabilityValidator
{
    /**
     * Memvalidasi ketersediaan alokasi kamar hotel untuk bundling paket festival/konser.
     *
     * @return array{is_available: bool, room_rate_idr: int, floor_cost_idr: int, reservation_token: ?string}
     */
    public function validateAvailability(
        string $hotelCode,
        string $roomType,
        int $quantity,
        string $checkInDate,
        string $checkOutDate
    ): array;
}
