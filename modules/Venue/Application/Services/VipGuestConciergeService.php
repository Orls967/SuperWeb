<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Venue\Domain\Models\EntertainmentTableBooking;

final class VipGuestConciergeService
{
    /**
     * Dispatch personal VIP host and prepare cabana setup.
     *
     * @return array{
     *     booking_code: string,
     *     assigned_host: string,
     *     valet_spot_reserved: string,
     *     champagne_chilled: bool,
     *     welcome_amenities: list<string>,
     *     status: string
     * }
     */
    public function prepareVipArrival(EntertainmentTableBooking $booking, string $conciergeHostName): array
    {
        $tableNum = $booking->table_number ?? 'CABANA-01';
        $valetSpot = 'VALET-VIP-'.substr($tableNum, -2);

        $amenities = [
            'Handmade Ice Carving with Event Logo',
            'Chilled Crystal Champagne Flutes (×6)',
            'Fresh Tropical Fruit Platter & Canapés',
            'Dedicated Security Escort to Table',
        ];

        return [
            'booking_code' => $booking->booking_code,
            'assigned_host' => $conciergeHostName,
            'valet_spot_reserved' => $valetSpot,
            'champagne_chilled' => true,
            'welcome_amenities' => $amenities,
            'status' => 'HOST_STAGED_AT_VALET',
        ];
    }

    /**
     * Log custom engraving or special requests on bottle service.
     */
    public function recordCustomEngraving(
        EntertainmentTableBooking $booking,
        string $bottleSku,
        string $engravingText
    ): array {
        return [
            'booking_code' => $booking->booking_code,
            'bottle_sku' => $bottleSku,
            'engraving_text' => $engravingText,
            'laser_station' => 'ENGRAVER-STATION-VIP-01',
            'completed_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }
}
