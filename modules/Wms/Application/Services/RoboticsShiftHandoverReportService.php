<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Modules\Core\Contracts\ClockInterface;
use Modules\Wms\Domain\Algorithms\ChargingBayContentionResolver;
use Modules\Wms\Domain\StateMachines\RobotMissionStateMachine;
use Modules\Wms\Models\Robot;
use Modules\Wms\Models\RobotChargeSchedule;
use Modules\Wms\Models\RobotMission;
use Modules\Wms\Models\RobotSafetyIncident;
use Modules\Wms\Models\TrafficZone;

/**
 * Menyusun ringkasan serah-terima shift operator gudang untuk Control Tower Robotika (Fase 279).
 */
final readonly class RoboticsShiftHandoverReportService
{
    public function __construct(
        private RoboticsFleetReadinessService $readinessService,
        private ChargingBayContentionResolver $bayResolver,
        private RoboticsTwoSourceAuditService $twoSourceAudit,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{
     *     warehouse_id:string,
     *     generated_at:string,
     *     shift_label:string,
     *     readiness:array<string, mixed>,
     *     active_missions_count:int,
     *     fallback_missions_count:int,
     *     blocked_zones:list<string>,
     *     low_battery_robots:list<string>,
     *     charger_bay_plan:array<string, mixed>,
     *     open_incidents_count:int,
     *     audit_state_hash:string,
     *     audit_passed:bool
     * }
     */
    public function buildHandover(string $warehouseId, string $shiftLabel = 'SHIFT-PAGI'): array
    {
        $now = $this->clock->now();
        $readiness = $this->readinessService->evaluateReadiness($warehouseId);

        $activeMissionsCount = RobotMission::query()
            ->where('warehouse_id', $warehouseId)
            ->whereIn('status', [
                RobotMissionStateMachine::STATUS_QUEUED,
                RobotMissionStateMachine::STATUS_ASSIGNED,
                RobotMissionStateMachine::STATUS_MOVING,
                RobotMissionStateMachine::STATUS_PICKING,
                RobotMissionStateMachine::STATUS_DELIVERING,
            ])
            ->count();

        $fallbackMissionsCount = RobotMission::query()
            ->where('warehouse_id', $warehouseId)
            ->where('status', RobotMissionStateMachine::STATUS_FALLBACK_MANUAL)
            ->count();

        $blockedZones = TrafficZone::query()
            ->where('warehouse_id', $warehouseId)
            ->where('staff_blocked', true)
            ->orderBy('zone_code')
            ->pluck('zone_code')
            ->map(static fn ($code): string => (string) $code)
            ->all();

        $lowBatteryRobots = Robot::query()
            ->where('warehouse_id', $warehouseId)
            ->where('battery_pct', '<', 25)
            ->orderBy('robot_code')
            ->pluck('robot_code')
            ->map(static fn ($code): string => (string) $code)
            ->all();

        $chargeRequests = RobotChargeSchedule::query()
            ->where('warehouse_id', $warehouseId)
            ->orderBy('window_start_min')
            ->get()
            ->map(static fn (RobotChargeSchedule $s): array => [
                'robot_id' => (int) $s->robot_id,
                'window_start_min' => (int) $s->window_start_min,
                'window_end_min' => (int) $s->window_end_min,
            ])
            ->all();

        $bayPlan = $this->bayResolver->assignBays($chargeRequests);

        $openIncidents = RobotSafetyIncident::query()
            ->where('warehouse_id', $warehouseId)
            ->where('status', 'reported')
            ->count();

        $audit = $this->twoSourceAudit->runAudit();

        return [
            'warehouse_id' => $warehouseId,
            'generated_at' => $now->toIso8601String(),
            'shift_label' => $shiftLabel,
            'readiness' => $readiness,
            'active_missions_count' => $activeMissionsCount,
            'fallback_missions_count' => $fallbackMissionsCount,
            'blocked_zones' => $blockedZones,
            'low_battery_robots' => $lowBatteryRobots,
            'charger_bay_plan' => $bayPlan,
            'open_incidents_count' => $openIncidents,
            'audit_state_hash' => (string) $audit['state_hash'],
            'audit_passed' => (bool) $audit['ok'],
        ];
    }
}
