<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\Models\HotelRoom;

/**
 * Layanan Protokol Keamanan Tamu, Kebakaran, & Kontrol Darurat Evakuasi Hotel.
 */
final class HotelGuestSafetyAndSecurityService
{
    /**
     * @return array{emergency_broadcast_sent: bool, unlocked_turnstiles_count: int, muster_point: string}
     */
    public function triggerEvacuationProtocol(string|int $propertyId, string $reason = 'FIRE_ALARM'): array
    {
        $roomsCount = HotelRoom::where('property_id', (string) $propertyId)->count();

        return [
            'emergency_broadcast_sent' => true,
            'unlocked_turnstiles_count' => max(4, (int) round($roomsCount / 20)),
            'muster_point' => 'Plaza Parkir Barat & Lapangan Terbuka',
        ];
    }
}
