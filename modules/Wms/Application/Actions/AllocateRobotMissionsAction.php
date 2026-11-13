<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Shared\Domain\Algorithms\Assignment\HungarianSolver;
use Modules\Wms\Application\Services\RobotMissionTransitionService;
use Modules\Wms\Domain\StateMachines\RobotMissionStateMachine;
use Modules\Wms\Models\Robot;
use Modules\Wms\Models\RobotMission;

/**
 * Action alokasi misi robot memakai HungarianSolver (Shared) dengan matriks biaya bulat (D279-3, D95).
 *
 * Robot offline atau baterai < ambang dikeluarkan dari matriks; misi tanpa pasangan tetap queued.
 */
final readonly class AllocateRobotMissionsAction
{
    public const int WEIGHT_DISTANCE = 10;

    public const int WEIGHT_BATTERY_PENALTY = 5;

    public const int MIN_BATTERY_PCT = 15;

    public function __construct(
        private HungarianSolver $hungarianSolver,
        private RobotMissionTransitionService $transitionService,
    ) {}

    /**
     * @return array{
     *     allocated: list<array{mission_id: int, mission_code: string, robot_id: int, robot_code: string, cost: int}>,
     *     unassigned_mission_ids: list<int>,
     *     total_cost: int,
     *     window_key: string
     * }
     */
    public function execute(string $warehouseId, string $windowKey, ?int $actorUserId = null): array
    {
        return DB::transaction(function () use ($warehouseId, $windowKey, $actorUserId): array {
            $missions = RobotMission::query()
                ->where('warehouse_id', $warehouseId)
                ->where('status', RobotMissionStateMachine::STATUS_QUEUED)
                ->orderByDesc('priority')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->values();

            $robots = Robot::query()
                ->where('warehouse_id', $warehouseId)
                ->where('is_online', true)
                ->where('battery_pct', '>=', self::MIN_BATTERY_PCT)
                ->whereDoesntHave('missions', static function ($q): void {
                    $q->whereIn('status', [
                        RobotMissionStateMachine::STATUS_ASSIGNED,
                        RobotMissionStateMachine::STATUS_MOVING,
                        RobotMissionStateMachine::STATUS_PICKING,
                        RobotMissionStateMachine::STATUS_DELIVERING,
                    ]);
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->values();

            if ($missions->isEmpty() || $robots->isEmpty()) {
                return [
                    'allocated' => [],
                    'unassigned_mission_ids' => $missions->pluck('id')->map(static fn ($id): int => (int) $id)->all(),
                    'total_cost' => 0,
                    'window_key' => $windowKey,
                ];
            }

            // Batasi matriks ke min(missions, robots) jika jumlah berbeda agar persegi atau pad dummy
            $nMissions = $missions->count();
            $nRobots = $robots->count();
            $dim = max($nMissions, $nRobots);
            $dummyCost = 500_000_000; // Strictly < HungarianSolver::INF (1_000_000_000) for dummy pad cells

            $costMatrix = [];
            for ($i = 0; $i < $dim; $i++) {
                $row = [];
                for ($j = 0; $j < $dim; $j++) {
                    if ($i >= $nMissions || $j >= $nRobots) {
                        $row[] = $dummyCost;

                        continue;
                    }
                    /** @var RobotMission $m */
                    $m = $missions[$i];
                    /** @var Robot $r */
                    $r = $robots[$j];
                    $zoneHashDist = (abs(crc32((string) $m->zone_code) % 50) + (int) $r->position_x + (int) $r->position_y);
                    $batteryPenalty = (100 - (int) $r->battery_pct) * self::WEIGHT_BATTERY_PENALTY;
                    $priorityPenalty = max(0, 100 - (int) $m->priority) * 50;
                    $row[] = ($zoneHashDist * self::WEIGHT_DISTANCE) + $batteryPenalty + $priorityPenalty;
                }
                $costMatrix[] = $row;
            }

            $solution = $this->hungarianSolver->solve($costMatrix);
            $allocated = [];
            $unassigned = [];
            $totalRealCost = 0;

            for ($i = 0; $i < $nMissions; $i++) {
                /** @var RobotMission $mission */
                $mission = $missions[$i];
                $robotIdx = $solution->assignment[$i] ?? -1;

                if ($robotIdx < 0 || $robotIdx >= $nRobots) {
                    $unassigned[] = (int) $mission->id;

                    continue;
                }

                /** @var Robot $robot */
                $robot = $robots[$robotIdx];
                $cost = (int) $costMatrix[$i][$robotIdx];
                $totalRealCost += $cost;

                $mission->robot_id = $robot->id;
                $mission->save();

                $this->transitionService->transition(
                    $mission,
                    RobotMissionStateMachine::STATUS_ASSIGNED,
                    $actorUserId,
                    'HUNGARIAN_ALLOCATION',
                    "Dialokasikan ke {$robot->robot_code} pada jendela {$windowKey}",
                    [
                        'has_hungarian_allocation' => true,
                        'robot_online' => (bool) $robot->is_online,
                        'robot_battery_pct' => (int) $robot->battery_pct,
                    ],
                );

                $allocated[] = [
                    'mission_id' => (int) $mission->id,
                    'mission_code' => (string) $mission->mission_code,
                    'robot_id' => (int) $robot->id,
                    'robot_code' => (string) $robot->robot_code,
                    'cost' => $cost,
                ];
            }

            return [
                'allocated' => $allocated,
                'unassigned_mission_ids' => $unassigned,
                'total_cost' => $totalRealCost,
                'window_key' => $windowKey,
            ];
        });
    }
}
