<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Modules\Hotel\Domain\Models\HotelHousekeepingTask;
use Modules\Hotel\Domain\StateMachines\HousekeepingTaskStateMachine;

/**
 * Tindakan Penugasan Prioritas Pembersihan Kamar (VIP & Early Checkin).
 */
final class AssignHousekeepingRoomPriorityAction
{
    /**
     * @return array{updated_task_id: int, new_priority: int}
     */
    public function execute(string|int $roomId, int $priorityScore, ?string $assignedStaffId = null): array
    {
        $task = HotelHousekeepingTask::where('room_id', (string) $roomId)
            ->whereIn('status', [
                HousekeepingTaskStateMachine::STATUS_PENDING,
                HousekeepingTaskStateMachine::STATUS_ASSIGNED,
            ])
            ->latest()
            ->firstOrFail();

        $task->update([
            'priority' => $priorityScore,
            'assigned_to_user_id' => $assignedStaffId ?? $task->assigned_to_user_id,
        ]);

        return [
            'updated_task_id' => (int) $task->id,
            'new_priority' => $priorityScore,
        ];
    }
}
