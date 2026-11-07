<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Modules\Hotel\Domain\Adapters\AssetWorkOrderAdapter;
use Modules\Hotel\Domain\Models\HotelRoom;

/**
 * Tindakan Pembuatan Tiket Work Order Perbaikan Kamar & Pengalihan Status Kamar.
 */
final class DispatchEngineeringTicketForDamagedRoomAction
{
    /**
     * @return array{room_id: string, work_order_ticket: string, new_status: string}
     */
    public function execute(
        string|int $roomId,
        string $issueCategory,
        string $description,
        string $severity = 'CRITICAL'
    ): array {
        $room = HotelRoom::findOrFail((string) $roomId);

        $roomIdInt = is_numeric($roomId) ? (int) $roomId : crc32((string) $roomId);
        $woRes = AssetWorkOrderAdapter::createMaintenanceOrder(
            roomId: $roomIdInt,
            issueType: $issueCategory,
            description: $description
        );

        $newStatus = ($severity === 'CRITICAL') ? 'out_of_order' : $room->status;
        $room->update(['status' => $newStatus]);

        return [
            'room_id' => (string) $roomId,
            'work_order_ticket' => (string) $woRes['work_order_id'],
            'new_status' => $newStatus,
        ];
    }
}
