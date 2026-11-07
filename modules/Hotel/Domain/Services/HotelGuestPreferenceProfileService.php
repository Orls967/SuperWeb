<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\Models\HotelReservation;

/**
 * Layanan Profiling Preferensi Tamu & Rekomendasi Personalisasi Kamar.
 */
final class HotelGuestPreferenceProfileService
{
    /**
     * @return array{vip_tier: string, preferred_floor: string, pillow_type: string, breakfast_pref: string}
     */
    public function buildGuestProfile(string|int $guestUserId): array
    {
        $pastReservations = HotelReservation::where('guest_user_id', (string) $guestUserId)
            ->where('status', 'checked_out')
            ->get();

        $stayCount = $pastReservations->count();
        $tier = 'REGULAR';
        if ($stayCount >= 10) {
            $tier = 'PLATINUM';
        } elseif ($stayCount >= 5) {
            $tier = 'GOLD';
        } elseif ($stayCount >= 2) {
            $tier = 'SILVER';
        }

        return [
            'vip_tier' => $tier,
            'preferred_floor' => $stayCount > 3 ? 'HIGH_FLOOR' : 'STANDARD',
            'pillow_type' => 'HYPOALLERGENIC_FEATHER',
            'breakfast_pref' => 'INDONESIAN_BUFFET',
        ];
    }
}
