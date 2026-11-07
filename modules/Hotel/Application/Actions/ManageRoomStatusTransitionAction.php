<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Modules\Hotel\Domain\Models\HotelHousekeepingTask;
use Modules\Hotel\Domain\Models\HotelRoom;
use Modules\Hotel\Domain\StateMachines\HousekeepingTaskStateMachine;

/**
 * Tindakan Pengelolaan Siklus Transisi Status Kamar & Penugasan Tata Graha Otomatis.
 */
final class ManageRoomStatusTransitionAction
{
    /**
     * @return array{room_id: string, previous_status: string, current_status: string, task_triggered: bool}
     */
    public function transition(string|int $roomId, string $targetStatus, ?string $userId = null): array
    {
        $room = HotelRoom::findOrFail((string) $roomId);
        $prev = (string) $room->status;

        $validTransitions = [
            'occupied' => ['dirty'],
            'dirty' => ['cleaning', 'out_of_order'],
            'cleaning' => ['inspected', 'dirty'],
            'inspected' => ['available', 'dirty', 'occupied'],
            'available' => ['occupied', 'dirty', 'out_of_order'],
            'out_of_order' => ['dirty', 'cleaning', 'available'],
        ];

        if (!isset($validTransitions[$prev]) || !in_array($targetStatus, $validTransitions[$prev], true)) {
            abort(422, "Transisi status kamar tidak sah dari {$prev} ke {$targetStatus}.");
        }

        $room->update(['status' => $targetStatus]);
        $taskTriggered = false;

        if ($targetStatus === 'dirty') {
            HotelHousekeepingTask::firstOrCreate(
                [
                    'room_id' => (string) $roomId,
                    'task_date' => date('Y-m-d'),
                ],
                [
                    'assigned_to_user_id' => $userId,
                    'task_type' => 'checkout_clean',
                    'priority' => 1,
                    'status' => HousekeepingTaskStateMachine::STATUS_ASSIGNED,
                ]
            );
            $taskTriggered = true;
        }

        return [
            'room_id' => (string) $roomId,
            'previous_status' => $prev,
            'current_status' => $targetStatus,
            'task_triggered' => $taskTriggered,
        ];
    }
}
