<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Modules\Wms\Domain\Algorithms\RobotFleetDispatchOptimizer;
use Modules\Wms\Domain\Algorithms\RobotTaskAllocator;
use Modules\Wms\Domain\Algorithms\TrafficDeadlockDetector;
use Modules\Wms\Domain\Algorithms\WaveAutomationDispatcher;
use Modules\Wms\Domain\StateMachines\RobotMissionStateMachine;
use Modules\Wms\Models\Robot;
use Modules\Wms\Models\RobotMission;
use Modules\Wms\Models\RobotUnit;
use Modules\Wms\Models\RobotZoneLock;
use Modules\Wms\Models\TrafficZone;

/**
 * Orkestrator armada robotik gudang (Fase 279): menggabungkan J2 allocator,
 * J3 wave dispatcher, detektor deadlock, pengoptimal penugasan interlock-energi, dan ringkasan status armada.
 */
final readonly class RoboticsFleetOrchestratorService
{
    public function __construct(
        private RobotTaskAllocator $allocator,
        private WaveAutomationDispatcher $waveDispatcher,
        private TrafficDeadlockDetector $deadlockDetector,
        private RobotFleetDispatchOptimizer $dispatchOptimizer,
    ) {}

    /**
     * @param  list<array{task_code:string, zone:string, distance_m:int, priority:int}>  $tasks
     * @param  array<string, string>  $waitForEdges
     * @return array<string, mixed>
     */
    public function orchestrateWave(
        string $warehouseId,
        string $waveCode,
        array $tasks,
        array $waitForEdges = [],
    ): array {
        $deadlock = $this->deadlockDetector->detectCycle($waitForEdges);

        $idleRobots = RobotUnit::query()
            ->where('warehouse_id', $warehouseId)
            ->where('status', 'idle')
            ->where('battery_pct', '>=', 15)
            ->orderBy('id')
            ->get();

        $robotInputs = $idleRobots->map(static fn (RobotUnit $unit): array => [
            'id' => $unit->id,
            'code' => $unit->robot_code,
            'zone' => (string) ($unit->current_zone_code ?? 'Z-DEFAULT'),
            'battery_pct' => $unit->battery_pct,
        ])->values()->all();

        $wavePlan = $this->waveDispatcher->planWaveDispatch(
            waveCode: $waveCode,
            tasks: array_map(static fn (array $t): array => [
                'task_code' => $t['task_code'],
                'zone' => $t['zone'],
                'priority' => $t['priority'],
            ], $tasks),
            availableRobotIds: array_column($robotInputs, 'id'),
        );

        $allocation = $this->allocator->allocate(
            robots: $robotInputs,
            tasks: array_map(static fn (array $t): array => [
                'task_code' => $t['task_code'],
                'zone' => $t['zone'],
                'distance_m' => $t['distance_m'],
            ], $tasks),
        );

        return [
            'warehouse_id' => $warehouseId,
            'wave_plan' => $wavePlan,
            'allocation' => $allocation,
            'deadlock' => $deadlock,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function fleetSummary(): array
    {
        return [
            'total_units' => RobotUnit::query()->count(),
            'idle_units' => RobotUnit::query()->where('status', 'idle')->count(),
            'active_units' => RobotUnit::query()->where('status', 'active')->count(),
            'faulted_units' => RobotUnit::query()->where('status', 'faulted')->count(),
            'active_missions' => RobotMission::query()->whereNotIn('status', ['completed', 'fallback_manual', 'failed'])->count(),
            'fallback_missions' => RobotMission::query()->where('fallback_manual', true)->count(),
            'active_zone_locks' => RobotZoneLock::query()->where('lock_status', 'active')->count(),
        ];
    }

    /**
     * Mengevaluasi pratinjau penugasan optimal berbasis interlock & anggaran energi per gudang (tanpa mutasi DB).
     *
     * @return array{assignments:list<array<string, mixed>>, unassigned_mission_ids:list<int>, total_cost:int}
     */
    public function previewInterlockAwareDispatch(string $warehouseId): array
    {
        $zonesByCode = TrafficZone::query()
            ->where('warehouse_id', $warehouseId)
            ->get()
            ->keyBy('zone_code');

        $queuedMissions = RobotMission::query()
            ->where('warehouse_id', $warehouseId)
            ->where('status', RobotMissionStateMachine::STATUS_QUEUED)
            ->orderByDesc('priority')
            ->orderBy('id')
            ->limit(10)
            ->get()
            ->map(static function (RobotMission $m) use ($zonesByCode): array {
                /** @var TrafficZone|null $zone */
                $zone = $zonesByCode->get((string) $m->zone_code);

                return [
                    'mission_id' => (int) $m->id,
                    'zone_code' => (string) $m->zone_code,
                    'priority' => (int) $m->priority,
                    'distance_m' => 25,
                    'payload_kg' => 15,
                    'start_min' => 0,
                    'duration_min' => 5,
                    'staff_blocked' => (bool) ($zone?->staff_blocked ?? false),
                    'capacity_k' => max(1, (int) ($zone?->capacity_k ?? 1)),
                    'active_reservations' => [],
                ];
            })
            ->values()
            ->all();

        $robots = Robot::query()
            ->where('warehouse_id', $warehouseId)
            ->orderBy('id')
            ->get()
            ->map(static fn (Robot $r): array => [
                'robot_id' => (int) $r->id,
                'battery_pct' => (int) $r->battery_pct,
                'position_x' => (int) $r->position_x,
                'position_y' => (int) $r->position_y,
                'is_online' => (bool) $r->is_online,
            ])
            ->values()
            ->all();

        $result = $this->dispatchOptimizer->optimize($queuedMissions, $robots);

        return [
            'assignments' => $result['assignments'],
            'unassigned_mission_ids' => $result['unassigned_mission_ids'],
            'total_cost' => (int) $result['total_cost'],
        ];
    }
}
