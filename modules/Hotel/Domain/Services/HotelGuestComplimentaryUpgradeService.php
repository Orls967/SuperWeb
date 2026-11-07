<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\Models\HotelReservation;

/**
 * Layanan Pemberian Upgrade Kamar Gratis (Complimentary VIP Room Upgrade).
 */
final class HotelGuestComplimentaryUpgradeService
{
    /**
     * @return array{upgrade_approved: bool, from_room_type_id: string, to_room_type_id: string, reason: string}
     */
    public function evaluateUpgrade(string|int $reservationId, string $guestTier): array
    {
        $reservation = HotelReservation::findOrFail((string) $reservationId);

        if (! in_array($guestTier, ['PLATINUM', 'GOLD'], true)) {
            return [
                'upgrade_approved' => false,
                'from_room_type_id' => (string) $reservation->room_type_id,
                'to_room_type_id' => (string) $reservation->room_type_id,
                'reason' => 'Tingkat keanggotaan tidak memenuhi syarat complimentary upgrade',
            ];
        }

        return [
            'upgrade_approved' => true,
            'from_room_type_id' => (string) $reservation->room_type_id,
            'to_room_type_id' => 'room-type-suite',
            'reason' => "Complimentary upgrade untuk tamu VIP tier {$guestTier}",
        ];
    }
}
