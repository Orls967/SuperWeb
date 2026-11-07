<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Carbon\CarbonImmutable;
use Modules\Hotel\Domain\Models\HotelHousekeepingTask;
use Modules\Hotel\Domain\Models\HotelRoom;
use Modules\Hotel\Domain\StateMachines\HousekeepingTaskStateMachine;
use Modules\Shared\Domain\Algorithms\Routing\HousekeepingRouteSequencer;

/**
 * Layanan Penjadwalan & Pengurutan Tugas Tata Graha Teroptimasi (H3).
 */
final class HotelHousekeepingTaskDispatchService
{
    /**
     * @return array{tasks_dispatched: int, prioritized_sequence: list<array<string, mixed>>}
     */
    public function dispatchDailyPlan(string|int $propertyId, ?string $assignedUserId = null): array
    {
        $dirtyRooms = HotelRoom::where('property_id', (string) $propertyId)
            ->whereIn('status', ['dirty', 'cleaning', 'out_of_order'])
            ->get();

        $roomsInput = [];
        foreach ($dirtyRooms as $room) {
            $floor = 1;
            if (is_numeric($room->room_number) && strlen((string) $room->room_number) >= 3) {
                $floor = (int) substr((string) $room->room_number, 0, -2);
            }

            $roomsInput[] = [
                'room_id' => is_numeric($room->id) ? (int) $room->id : crc32((string) $room->id),
                'floor' => max(1, $floor),
                'room_number' => (int) filter_var($room->room_number, FILTER_SANITIZE_NUMBER_INT) ?: 101,
                'is_vip' => false,
                'is_checkout' => $room->status === 'dirty',
            ];
        }

        $sequenced = HousekeepingRouteSequencer::sequence(rooms: $roomsInput);

        foreach ($dirtyRooms as $room) {
            HotelHousekeepingTask::firstOrCreate(
                [
                    'room_id' => (string) $room->id,
                    'task_date' => CarbonImmutable::now()->toDateString(),
                ],
                [
                    'assigned_to_user_id' => $assignedUserId,
                    'task_type' => $room->status === 'dirty' ? 'checkout_clean' : 'stayover_clean',
                    'priority' => 1,
                    'status' => HousekeepingTaskStateMachine::STATUS_ASSIGNED,
                ]
            );
        }

        return [
            'tasks_dispatched' => count($dirtyRooms),
            'prioritized_sequence' => $sequenced['ordered_rooms'],
        ];
    }
}
