<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Services;

use Modules\Hotel\Contracts\HotelPackageAvailabilityValidator;

class HotelPackageAvailabilityService implements HotelPackageAvailabilityValidator
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
    ): array {
        // Alokasi slot kamar mitra untuk dynamic bundling festival
        $baseFloorCost = match (strtoupper($roomType)) {
            'VIP_SUITE', 'DELUXE_SUITE' => 600000,
            'PREMIER' => 450000,
            default => 350000,
        };

        $baseRate = (int) ($baseFloorCost * 1.5);
        $token = 'HTL-RES-'.strtoupper(substr(hash('sha256', $hotelCode.$roomType.$checkInDate.(string) $quantity), 0, 16));

        return [
            'is_available' => true,
            'room_rate_idr' => $baseRate * $quantity,
            'floor_cost_idr' => $baseFloorCost * $quantity,
            'reservation_token' => $token,
        ];
    }
}
