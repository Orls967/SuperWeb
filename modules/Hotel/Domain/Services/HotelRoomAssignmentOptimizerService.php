<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoom;

/**
 * Layanan Optimasi Alokasi Kamar Tamu Berdasarkan Preferensi & Efisiensi Energi.
 */
final class HotelRoomAssignmentOptimizerService
{
    /**
     * @param array<string, mixed> $guestPreferences
     * @return array{assigned_room_id: string|null, score: int, match_reason: string}
     */
    public function findOptimalRoom(
        string|int $propertyId,
        string|int $roomTypeId,
        array $guestPreferences = []
    ): array {
        $availableRooms = HotelRoom::where('property_id', (string) $propertyId)
            ->where('room_type_id', (string) $roomTypeId)
            ->whereIn('status', ['inspected', 'available'])
            ->get();

        if ($availableRooms->isEmpty()) {
            return [
                'assigned_room_id' => null,
                'score' => 0,
                'match_reason' => 'Tidak ada kamar siap huni yang tersedia untuk tipe ini',
            ];
        }

        $highFloorPref = (bool) ($guestPreferences['high_floor'] ?? false);
        $quietRoomPref = (bool) ($guestPreferences['quiet_room'] ?? false);

        $bestRoom = null;
        $bestScore = -1;
        $reason = 'Kamar default tersedia pertama';

        foreach ($availableRooms as $room) {
            $score = 50; // base score
            $roomNum = (int) filter_var($room->room_number, FILTER_SANITIZE_NUMBER_INT);
            $floor = $roomNum >= 100 ? (int) substr((string) $roomNum, 0, -2) : 1;

            if ($highFloorPref && $floor >= 3) {
                $score += 30;
                $reason = 'Kamar lantai atas sesuai preferensi tamu';
            }

            if ($quietRoomPref && ($roomNum % 2 === 0)) {
                $score += 20;
                $reason = 'Kamar sisi tenang menjauhi koridor lift';
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestRoom = $room;
            }
        }

        return [
            'assigned_room_id' => $bestRoom ? (string) $bestRoom->id : null,
            'score' => $bestScore,
            'match_reason' => $reason,
        ];
    }
}
