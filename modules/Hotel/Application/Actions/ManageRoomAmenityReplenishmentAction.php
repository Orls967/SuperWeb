<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Modules\Hotel\Domain\Models\HotelHousekeepingTask;

/**
 * Tindakan Pencatatan Pemenuhan Fasilitas & Amenitas Kamar (Shampoo, Sabun, Slippers).
 */
final class ManageRoomAmenityReplenishmentAction
{
    /**
     * @param list<string> $replenishedItems
     * @return array{room_id: string, items_count: int, status: string}
     */
    public function execute(string|int $roomId, array $replenishedItems): array
    {
        $task = HotelHousekeepingTask::where('room_id', (string) $roomId)
            ->latest()
            ->first();

        return [
            'room_id' => (string) $roomId,
            'items_count' => count($replenishedItems),
            'status' => 'REPLENISHED',
        ];
    }
}
