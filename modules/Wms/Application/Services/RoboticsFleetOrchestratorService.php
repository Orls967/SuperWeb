<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Modules\Wms\Domain\Algorithms\RobotTaskAllocator;
use Modules\Wms\Domain\Algorithms\TrafficDeadlockDetector;
use Modules\Wms\Domain\Algorithms\WaveAutomationDispatcher;
use Modules\Wms\Models\RobotMission;
use Modules\Wms\Models\RobotUnit;
use Modules\Wms\Models\RobotZoneLock;

/**
 * Orkestrator armada robotik gudang (Fase 279): menggabungkan J2 allocator,
 * J3 wave dispatcher, detektor deadlock, dan ringkasan status armada.
 */
final readonly class RoboticsFleetOrchestratorService
{
    public function __construct(
        private RobotTaskAllocator $allocator,
        private WaveAutomationDispatcher $waveDispatcher,
        private TrafficDeadlockDetector $deadlockDetector,
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
}
