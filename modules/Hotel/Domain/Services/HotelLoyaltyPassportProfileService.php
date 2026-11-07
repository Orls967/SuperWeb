<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

/**
 * Layanan Domain: Profil Paspor Tamu, Riwayat Stay, dan Preferensi Kenyamanan Kamar.
 */
final class HotelLoyaltyPassportProfileService
{
    /**
     * @return array{guest_id: string, preferred_floor: string, pillow_type: string, dietary_allergies: array<int, string>, vip_tag: string}
     */
    public function getGuestPassportProfile(string $guestId): array
    {
        // Simulasi pembacaan preferensi tamu dari CRM profil loyalitas
        return [
            'guest_id' => $guestId,
            'preferred_floor' => 'HIGH_FLOOR_QUIET_ZONE',
            'pillow_type' => 'HYPOALLERGENIC_MEMORY_FOAM',
            'dietary_allergies' => ['PEANUTS', 'SHELLFISH', 'GLUTEN_SENSITIVE'],
            'vip_tag' => 'LOYALTY_PLATINUM_EXECUTIVE',
        ];
    }
}
