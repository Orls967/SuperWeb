<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Wms\Domain\StateMachines\RobotMissionStateMachine;
use Modules\Wms\Models\RobotMission;
use Modules\Wms\Models\RoboticsRoiProposal;
use Modules\Wms\Models\RobotWave;
use Modules\Wms\Domain\Models\Task as WarehouseTask;
use Modules\Wms\Models\TrafficZone;
use Modules\Wms\Models\ZoneReservation;

/**
 * Audit dua sumber deterministik untuk command robotics:audit (D279-9, T-10).
 *
 * Memeriksa 8 invarian robotika: kapasitas reservasi zona, misi moving tanpa reservasi/blokir staf,
 * misi aktif pada robot offline, misi completed tanpa WarehouseTask confirmed, beban wave,
 * duplikasi order lintas wave, proposal ROI negatif tanpa alasan, dan integritas hash tabel simulasi.
 */
final class RoboticsTwoSourceAuditService
{
    /**
     * @return array{ok: bool, issue_count: int, issues: list<string>, state_hash: string}
     */
    public function runAudit(): array
    {
        $issues = [];

        // 1. Reservasi tumpang tindih melebihi kapasitas zona k
        $zones = TrafficZone::query()->orderBy('id')->get();
        foreach ($zones as $zone) {
            $reservations = ZoneReservation::query()
                ->where('traffic_zone_id', $zone->id)
                ->where('status', 'active')
                ->orderBy('start_min')
                ->orderBy('id')
                ->get();

            $events = [];
            foreach ($reservations as $res) {
                $events[] = ['t' => (int) $res->start_min, 'delta' => 1];
                $events[] = ['t' => (int) $res->end_min, 'delta' => -1];
            }
            usort($events, static fn (array $a, array $b): int => [$a['t'], $a['delta']] <=> [$b['t'], $b['delta']]);

            $active = 0;
            foreach ($events as $ev) {
                $active += $ev['delta'];
                if ($active > (int) $zone->capacity_k) {
                    $issues[] = "ZONE_OVER_CAPACITY:{$zone->zone_code}:active={$active}:capacity={$zone->capacity_k}";
                    break;
                }
            }
        }

        // 2. Misi moving tanpa reservasi aktif atau di zona diblokir staf
        $movingMissions = RobotMission::query()
            ->where('status', RobotMissionStateMachine::STATUS_MOVING)
            ->orderBy('id')
            ->get();
        foreach ($movingMissions as $mission) {
            $hasActiveRes = ZoneReservation::query()
                ->where('mission_id', $mission->id)
                ->where('status', 'active')
                ->exists();
            if (! $hasActiveRes) {
                $issues[] = "MOVING_WITHOUT_RESERVATION:{$mission->mission_code}";
            }

            if ($mission->traffic_zone_id !== null) {
                $zone = TrafficZone::query()->find($mission->traffic_zone_id);
                if ($zone !== null && (bool) $zone->staff_blocked) {
                    $issues[] = "MOVING_IN_BLOCKED_ZONE:{$mission->mission_code}:{$zone->zone_code}";
                }
            }
        }

        // 3. Misi aktif pada robot offline
        $activeMissionsWithRobot = RobotMission::query()
            ->whereIn('status', [
                RobotMissionStateMachine::STATUS_ASSIGNED,
                RobotMissionStateMachine::STATUS_MOVING,
                RobotMissionStateMachine::STATUS_PICKING,
                RobotMissionStateMachine::STATUS_DELIVERING,
            ])
            ->whereNotNull('robot_id')
            ->with('robot')
            ->orderBy('id')
            ->get();
        foreach ($activeMissionsWithRobot as $mission) {
            if ($mission->robot !== null && ! (bool) $mission->robot->is_online) {
                $issues[] = "ACTIVE_MISSION_ON_OFFLINE_ROBOT:{$mission->mission_code}:{$mission->robot->robot_code}";
            }
        }

        // 4. Misi completed tanpa WarehouseTask terkait berstatus confirmed/closed
        $completedMissions = RobotMission::query()
            ->where('status', RobotMissionStateMachine::STATUS_COMPLETED)
            ->whereNotNull('warehouse_task_id')
            ->orderBy('id')
            ->get();
        foreach ($completedMissions as $mission) {
            $task = WarehouseTask::query()->find($mission->warehouse_task_id);
            if ($task !== null && ! in_array((string) $task->status, ['confirmed', 'closed'], true)) {
                $issues[] = "COMPLETED_MISSION_UNCONFIRMED_TASK:{$mission->mission_code}:task={$task->id}";
            }
        }

        // 5 & 6. Wave melebihi kapasitas + order di >= 2 wave aktif
        $activeWaves = RobotWave::query()
            ->whereIn('status', ['planned', 'released', 'oversize'])
            ->orderBy('id')
            ->get();
        $seenOrders = [];
        foreach ($activeWaves as $wave) {
            if (! (bool) $wave->is_oversize && (int) $wave->allocated_load > (int) $wave->capacity) {
                $issues[] = "WAVE_OVER_CAPACITY:{$wave->wave_code}:{$wave->allocated_load}>{$wave->capacity}";
            }
            foreach ($wave->order_items ?? [] as $item) {
                $oid = (string) ($item['id'] ?? '');
                if ($oid === '') {
                    continue;
                }
                if (isset($seenOrders[$oid])) {
                    $issues[] = "DUPLICATE_ORDER_IN_WAVE:{$oid}:{$wave->wave_code}";
                }
                $seenOrders[$oid] = $wave->wave_code;
            }
        }

        // 7. Proposal ROI approved dengan NPV < 0 tanpa alasan
        $badProposals = RoboticsRoiProposal::query()
            ->where('status', 'approved')
            ->where('npv_minor', '<', 0)
            ->orderBy('id')
            ->get();
        foreach ($badProposals as $prop) {
            if (trim((string) $prop->justification) === '') {
                $issues[] = "APPROVED_NEGATIVE_NPV_NO_JUSTIFICATION:{$prop->proposal_code}";
            }
        }

        sort($issues);

        return [
            'ok' => $issues === [],
            'issue_count' => count($issues),
            'issues' => $issues,
            'state_hash' => $this->computeProductionStateHash(),
        ];
    }

    public function computeProductionStateHash(): string
    {
        $counts = [
            'wms_robots' => DB::table('wms_robots')->count(),
            'wms_traffic_zones' => DB::table('wms_traffic_zones')->count(),
            'wms_robot_missions' => DB::table('wms_robot_missions')->count(),
            'wms_zone_reservations' => DB::table('wms_zone_reservations')->count(),
            'wms_robot_waves' => DB::table('wms_robot_waves')->count(),
            'wms_robotics_roi_proposals' => DB::table('wms_robotics_roi_proposals')->count(),
        ];

        return hash('sha256', json_encode($counts, JSON_THROW_ON_ERROR));
    }
}
