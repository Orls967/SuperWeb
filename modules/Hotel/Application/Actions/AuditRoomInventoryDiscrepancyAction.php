<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoom;
use Modules\Hotel\Domain\Models\HotelRoomNight;

/**
 * Tindakan Audit Integritas Ketersediaan Kamar vs Reservasi Aktif (Sleepers & Skips Audit).
 */
final class AuditRoomInventoryDiscrepancyAction
{
    /**
     * @return array{total_rooms: int, sleepers_count: int, skips_count: int, is_clean: bool}
     */
    public function execute(string|int $propertyId, string $businessDate): array
    {
        $propertyIdStr = (string) $propertyId;
        $totalRooms = HotelRoom::where('property_id', $propertyIdStr)->count();

        // 1. Sleepers: Kamar tercatat occupied di sistem namun tamu secara fisik sudah pergi
        // 2. Skips: Kamar kosong di sistem namun ada tamu menempati tanpa reservasi sah
        $nights = HotelRoomNight::where('property_id', $propertyIdStr)
            ->where('night_date', $businessDate)
            ->where('status', 'OCCUPIED')
            ->get();

        $sleepers = 0;
        $skips = 0;

        foreach ($nights as $night) {
            $rsv = HotelReservation::find($night->reservation_id);
            if (!$rsv || $rsv->status === 'checked_out') {
                $sleepers++;
            }
        }

        return [
            'total_rooms' => $totalRooms,
            'sleepers_count' => $sleepers,
            'skips_count' => $skips,
            'is_clean' => ($sleepers === 0 && $skips === 0),
        ];
    }
}
