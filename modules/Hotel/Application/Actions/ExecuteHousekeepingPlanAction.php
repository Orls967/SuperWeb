<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Hotel\Domain\Models\HotelHousekeepingTask;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelRoom;
use Modules\Hotel\Domain\StateMachines\HousekeepingTaskStateMachine;
use Modules\Shared\Domain\Algorithms\Routing\HousekeepingRouteSequencer;

/**
 * Penyusunan & Eksekusi Rencana Rute Housekeeping H3 (D91-6)
 */
final class ExecuteHousekeepingPlanAction
{
    /**
     * @param int $propertyId
     * @param string $taskDate (Y-m-d)
     * @return array{total_tasks: int, assigned_staff_count: int, unassigned_count: int}
     */
    public function execute(int $propertyId, string $taskDate): array
    {
        return DB::transaction(function () use ($propertyId, $taskDate): array {
            $rooms = HotelRoom::where('property_id', $propertyId)
                ->where('status', '!=', 'out_of_order')
                ->get();

            if ($rooms->isEmpty()) {
                return ['total_tasks' => 0, 'assigned_staff_count' => 0, 'unassigned_count' => 0];
            }

            // Ambil staf housekeeping
            $staffList = User::whereHas('roles', function ($q): void {
                $q->where('name', 'housekeeping');
            })->pluck('name', 'id')->all();

            if (empty($staffList)) {
                // Fallback default petugas jika belum ada
                $staffList = [1 => 'Petugas A', 2 => 'Petugas B'];
            }

            $roomPayload = [];
            foreach ($rooms as $r) {
                $roomPayload[] = [
                    'room' => (int) $r->id,
                    'credit' => 20,
                    'is_priority' => (bool) ($r->is_vip ?? false),
                    'is_vip' => (bool) ($r->is_vip ?? false),
                ];
            }

            $staffNames = array_values($staffList);
            $seqRes = HousekeepingRouteSequencer::sequence($roomPayload, $staffNames, 240);

            // Simpan tasks ke DB
            $staffIdByName = array_flip($staffList);

            foreach ($seqRes['routes'] as $staffName => $routeRooms) {
                $staffId = $staffIdByName[$staffName] ?? null;
                $sequence = 1;
                foreach ($routeRooms as $rId) {
                    HotelHousekeepingTask::updateOrCreate(
                        [
                            'property_id' => $propertyId,
                            'room_id' => $rId,
                            'task_date' => $taskDate,
                        ],
                        [
                            'assigned_user_id' => $staffId,
                            'credit_points' => 20,
                            'route_sequence' => $sequence++,
                            'status' => HousekeepingTaskStateMachine::STATUS_ASSIGNED,
                        ]
                    );
                }
            }

            return [
                'total_tasks' => count($roomPayload),
                'assigned_staff_count' => count($seqRes['routes']),
                'unassigned_count' => count($seqRes['unassigned_queue']),
            ];
        });
    }
}
