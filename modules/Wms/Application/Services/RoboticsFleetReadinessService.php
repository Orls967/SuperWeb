<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Modules\Wms\Domain\Algorithms\RobotBatteryDegradationTracker;
use Modules\Wms\Models\Robot;
use Modules\Wms\Models\RobotChargeSchedule;
use Modules\Wms\Models\RoboticsRoiProposal;
use Modules\Wms\Models\RobotSafetyIncident;
use Modules\Wms\Models\TrafficZone;

/**
 * Mengevaluasi kesiapan operasional pra-shift armada robotika gudang (Control Tower Readiness Check).
 */
final readonly class RoboticsFleetReadinessService
{
    public const int MIN_ONLINE_RATIO_PERMIL = 700;

    public function __construct(
        private RobotBatteryDegradationTracker $batteryTracker,
        private RoboticsTwoSourceAuditService $twoSourceAudit,
    ) {}

    /**
     * @return array{
     *     warehouse_id:string,
     *     ready:bool,
     *     online_ratio_permil:int,
     *     battery_avg_health_permil:int,
     *     unblocked_zones:int,
     *     open_incidents:int,
     *     pending_roi_count:int,
     *     scheduled_chargers:int,
     *     checks:list<array{code:string, passed:bool, detail:string}>
     * }
     */
    public function evaluateReadiness(string $warehouseId): array
    {
        $robots = Robot::query()->where('warehouse_id', $warehouseId)->orderBy('id')->get();
        $totalRobots = $robots->count();
        $onlineRobots = $robots->where('is_online', true)->count();
        $onlineRatioPermil = $totalRobots > 0 ? intdiv($onlineRobots * 1_000, $totalRobots) : 0;

        $batteryInput = $robots->map(static fn (Robot $r): array => [
            'robot_id' => (int) $r->id,
            'cycle_count' => max(10, (100 - (int) $r->battery_pct) * 4),
            'deep_discharge_events' => (int) $r->battery_pct < 15 ? 2 : 0,
        ])->values()->all();
        $batteryCert = $this->batteryTracker->evaluateFleet($batteryInput);

        $zones = TrafficZone::query()->where('warehouse_id', $warehouseId)->get();
        $unblockedZones = $zones->where('staff_blocked', false)->count();
        $openIncidents = RobotSafetyIncident::query()->where('warehouse_id', $warehouseId)->where('status', 'reported')->count();
        $pendingRoi = RoboticsRoiProposal::query()->where('warehouse_id', $warehouseId)->where('status', 'pending')->count();
        $scheduledChargers = RobotChargeSchedule::query()->where('warehouse_id', $warehouseId)->count();
        $audit = $this->twoSourceAudit->runAudit();

        $checks = [
            [
                'code' => 'FLEET_ONLINE_RATIO',
                'passed' => $onlineRatioPermil >= self::MIN_ONLINE_RATIO_PERMIL,
                'detail' => "Rasio armada online {$onlineRatioPermil} permil (min ".self::MIN_ONLINE_RATIO_PERMIL.').',
            ],
            [
                'code' => 'UNBLOCKED_ZONE_COVERAGE',
                'passed' => $unblockedZones >= 1,
                'detail' => "Tersedia {$unblockedZones} zona terbuka dari {$zones->count()} total zona.",
            ],
            [
                'code' => 'TWO_SOURCE_AUDIT_CLEAN',
                'passed' => (bool) $audit['ok'],
                'detail' => $audit['ok'] ? 'Audit dua sumber bersih.' : "Ditemukan {$audit['issue_count']} isu audit.",
            ],
            [
                'code' => 'SAFETY_INCIDENTS_CLEAR',
                'passed' => $openIncidents === 0,
                'detail' => "Insiden keselamatan berstatus reported: {$openIncidents}.",
            ],
        ];

        $ready = true;
        foreach ($checks as $check) {
            if (! $check['passed']) {
                $ready = false;
                break;
            }
        }

        return [
            'warehouse_id' => $warehouseId,
            'ready' => $ready,
            'online_ratio_permil' => $onlineRatioPermil,
            'battery_avg_health_permil' => $batteryCert->fleetAvgHealthPermil,
            'unblocked_zones' => $unblockedZones,
            'open_incidents' => $openIncidents,
            'pending_roi_count' => $pendingRoi,
            'scheduled_chargers' => $scheduledChargers,
            'checks' => $checks,
        ];
    }
}
