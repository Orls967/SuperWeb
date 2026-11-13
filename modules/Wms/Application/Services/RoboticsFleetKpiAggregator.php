<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Modules\Wms\Domain\Algorithms\ZoneCongestionHeatmapCalculator;
use Modules\Wms\Domain\StateMachines\RobotMissionStateMachine;
use Modules\Wms\Models\Robot;
use Modules\Wms\Models\RobotMission;
use Modules\Wms\Models\RobotSafetyIncident;
use Modules\Wms\Models\TrafficZone;
use Modules\Wms\Models\ZoneReservation;

/**
 * Agregator KPI armada & keselamatan robotika berbasis fakta (tanpa float, T-6b).
 */
final readonly class RoboticsFleetKpiAggregator
{
    public function __construct(
        private ZoneCongestionHeatmapCalculator $heatmapCalculator,
    ) {}

    /**
     * @return array<string, int>
     */
    public function summarize(string $warehouseId): array
    {
        $totalRobots = Robot::query()->where('warehouse_id', $warehouseId)->count();
        $onlineRobots = Robot::query()->where('warehouse_id', $warehouseId)->where('is_online', true)->count();
        $totalMissions = RobotMission::query()->where('warehouse_id', $warehouseId)->count();
        $completedMissions = RobotMission::query()
            ->where('warehouse_id', $warehouseId)
            ->where('status', RobotMissionStateMachine::STATUS_COMPLETED)
            ->count();
        $fallbackMissions = RobotMission::query()
            ->where('warehouse_id', $warehouseId)
            ->where('status', RobotMissionStateMachine::STATUS_FALLBACK_MANUAL)
            ->count();

        $sumHealth = (int) Robot::query()->where('warehouse_id', $warehouseId)->sum('health_score');
        $avgHealth = $totalRobots > 0 ? intdiv($sumHealth, $totalRobots) : 0;
        $openIncidents = RobotSafetyIncident::query()
            ->where('warehouse_id', $warehouseId)
            ->where('status', 'reported')
            ->count();

        $completionPermil = $totalMissions > 0 ? intdiv($completedMissions * 1000, $totalMissions) : 0;
        $fallbackPermil = $totalMissions > 0 ? intdiv($fallbackMissions * 1000, $totalMissions) : 0;

        $firstZone = TrafficZone::query()->where('warehouse_id', $warehouseId)->orderBy('id')->first();
        $peakUtilPermil = 0;
        if ($firstZone !== null) {
            $resList = ZoneReservation::query()
                ->where('traffic_zone_id', $firstZone->id)
                ->where('status', 'active')
                ->get()
                ->map(static fn (ZoneReservation $r): array => ['start' => (int) $r->start_min, 'end' => (int) $r->end_min])
                ->all();
            $heat = $this->heatmapCalculator->analyze(max(1, (int) $firstZone->capacity_k), $resList);
            $peakUtilPermil = $heat['peak_utilization_permil'];
        }

        return [
            'total_robots' => $totalRobots,
            'online_robots' => $onlineRobots,
            'total_missions' => $totalMissions,
            'completed_missions' => $completedMissions,
            'fallback_missions' => $fallbackMissions,
            'completion_permil' => $completionPermil,
            'fallback_permil' => $fallbackPermil,
            'avg_health_score' => $avgHealth,
            'open_safety_incidents' => $openIncidents,
            'peak_zone_utilization_permil' => $peakUtilPermil,
        ];
    }
}
