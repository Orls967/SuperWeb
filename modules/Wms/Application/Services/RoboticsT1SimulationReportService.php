<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Modules\Wms\Domain\Algorithms\RoboticsRoiSensitivityMatrixBuilder;
use Modules\Wms\Domain\Algorithms\ZoneCongestionHeatmapCalculator;
use Modules\Wms\Domain\StateMachines\RobotMissionStateMachine;
use Modules\Wms\Domain\ValueObjects\RationalRate;
use Modules\Wms\Models\Robot;
use Modules\Wms\Models\RobotChargeSchedule;
use Modules\Wms\Models\RoboticsRoiProposal;
use Modules\Wms\Models\RobotMission;
use Modules\Wms\Models\RobotSafetyIncident;
use Modules\Wms\Models\RobotWave;
use Modules\Wms\Models\TrafficZone;
use Modules\Wms\Models\ZoneReservation;

/**
 * Menyusun laporan deterministik hasil simulasi T1, kondisi armada, kemacetan zona, portofolio ROI,
 * dan kepatuhan audit dua sumber untuk Control Tower Robotika (Fase 279).
 */
final readonly class RoboticsT1SimulationReportService
{
    public function __construct(
        private RoboticsFleetKpiAggregator $kpiAggregator,
        private RoboticsTwoSourceAuditService $twoSourceAudit,
        private ZoneCongestionHeatmapCalculator $heatmapCalculator,
        private RoboticsRoiSensitivityMatrixBuilder $sensitivityBuilder,
    ) {}

    /**
     * @return array{
     *     warehouse_id:string,
     *     kpi:array<string, mixed>,
     *     status_breakdown:array<string, int>,
     *     zones_summary:list<array{zone_code:string, capacity_k:int, staff_blocked:bool, active_reservations:int, congestion_level:string}>,
     *     roi_portfolio:array{total_proposals:int, approved_count:int, negative_npv_count:int, total_npv_minor:int, sensitivity_sample:list<array<string, int>>},
     *     wave_summary:array{total_waves:int, total_orders:int, max_utilization_pct:int},
     *     charging_summary:array{scheduled_slots:int, avg_target_battery_pct:int},
     *     safety_summary:array{total_incidents:int, open_incidents:int},
     *     audit_status:array{passed:bool, error_count:int, summary:array<string, int>}
     * }
     */
    public function buildReport(string $warehouseId): array
    {
        $kpi = $this->kpiAggregator->summarize($warehouseId);

        $statusBreakdown = [
            RobotMissionStateMachine::STATUS_QUEUED => 0,
            RobotMissionStateMachine::STATUS_ASSIGNED => 0,
            RobotMissionStateMachine::STATUS_MOVING => 0,
            RobotMissionStateMachine::STATUS_PICKING => 0,
            RobotMissionStateMachine::STATUS_DELIVERING => 0,
            RobotMissionStateMachine::STATUS_COMPLETED => 0,
            RobotMissionStateMachine::STATUS_FAILED => 0,
            RobotMissionStateMachine::STATUS_FALLBACK_MANUAL => 0,
        ];
        $grouped = RobotMission::query()
            ->where('warehouse_id', $warehouseId)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
        foreach ($grouped as $status => $total) {
            $statusBreakdown[(string) $status] = (int) $total;
        }

        $zones = TrafficZone::query()->where('warehouse_id', $warehouseId)->orderBy('zone_code')->get();
        $activeRes = ZoneReservation::query()
            ->where('warehouse_id', $warehouseId)
            ->where('status', 'active')
            ->get();

        $resByZone = $activeRes->groupBy('traffic_zone_id');
        $zonesSummary = [];
        foreach ($zones as $z) {
            $zRes = $resByZone->get($z->id, collect());
            $resList = $zRes->map(static fn ($r): array => [
                'start' => (int) $r->start_min,
                'end' => (int) $r->end_min,
            ])->values()->all();
            $heat = $this->heatmapCalculator->analyze(max(1, (int) $z->capacity_k), $resList);
            $peakPermil = (int) ($heat['peak_utilization_permil'] ?? 0);
            $congestion = $peakPermil >= 800 ? 'HIGH' : ($peakPermil >= 400 ? 'MEDIUM' : 'LOW');

            $zonesSummary[] = [
                'zone_code' => (string) $z->zone_code,
                'capacity_k' => (int) $z->capacity_k,
                'staff_blocked' => (bool) $z->staff_blocked,
                'active_reservations' => $zRes->count(),
                'congestion_level' => $congestion,
            ];
        }

        $proposals = RoboticsRoiProposal::query()->where('warehouse_id', $warehouseId)->orderBy('id')->get();
        $approvedCount = $proposals->where('status', 'approved')->count();
        $negNpvCount = $proposals->filter(static fn ($p): bool => (int) $p->npv_minor < 0)->count();
        $totalNpv = (int) $proposals->sum('npv_minor');

        $sensitivitySample = [];
        if ($proposals->isNotEmpty()) {
            /** @var RoboticsRoiProposal $firstProp */
            $firstProp = $proposals->first();
            $cashflows = is_array($firstProp->cash_flows_minor) ? $firstProp->cash_flows_minor : [400_000_000, 400_000_000, 400_000_000];
            $gridResult = $this->sensitivityBuilder->buildGrid(
                (int) $firstProp->investment_minor,
                array_map('intval', $cashflows),
                new RationalRate(max(1, (int) ($firstProp->discount_rate_num ?: 1)), max(1, (int) ($firstProp->discount_rate_den ?: 10))),
            );
            $sensitivitySample = $gridResult['cells'] ?? [];
        }

        $waves = RobotWave::query()->where('warehouse_id', $warehouseId)->where('status', '!=', 'superseded')->get();
        $totalOrders = 0;
        $maxUtilPct = 0;
        foreach ($waves as $w) {
            $orders = is_array($w->order_items) ? $w->order_items : [];
            $totalOrders += count($orders);
            if ((int) $w->capacity > 0) {
                $util = intdiv((int) $w->allocated_load * 100, (int) $w->capacity);
                if ($util > $maxUtilPct) {
                    $maxUtilPct = $util;
                }
            }
        }

        $charges = RobotChargeSchedule::query()->where('warehouse_id', $warehouseId)->get();
        $avgTargetBattery = $charges->isEmpty() ? 0 : intdiv((int) $charges->sum('target_battery_pct'), $charges->count());

        $totalIncidents = RobotSafetyIncident::query()->where('warehouse_id', $warehouseId)->count();
        $openIncidents = RobotSafetyIncident::query()->where('warehouse_id', $warehouseId)->where('status', 'reported')->count();

        $audit = $this->twoSourceAudit->runAudit();

        return [
            'warehouse_id' => $warehouseId,
            'kpi' => $kpi,
            'status_breakdown' => $statusBreakdown,
            'zones_summary' => $zonesSummary,
            'roi_portfolio' => [
                'total_proposals' => $proposals->count(),
                'approved_count' => $approvedCount,
                'negative_npv_count' => $negNpvCount,
                'total_npv_minor' => $totalNpv,
                'sensitivity_sample' => $sensitivitySample,
            ],
            'wave_summary' => [
                'total_waves' => $waves->count(),
                'total_orders' => $totalOrders,
                'max_utilization_pct' => $maxUtilPct,
            ],
            'charging_summary' => [
                'scheduled_slots' => $charges->count(),
                'avg_target_battery_pct' => $avgTargetBattery,
            ],
            'safety_summary' => [
                'total_incidents' => $totalIncidents,
                'open_incidents' => $openIncidents,
            ],
            'audit_status' => [
                'passed' => (bool) $audit['ok'],
                'error_count' => (int) $audit['issue_count'],
                'state_hash' => (string) $audit['state_hash'],
            ],
        ];
    }

    /**
     * Menghasilkan ringkasan teks terstruktur untuk keluaran CLI `robotics:simulate`.
     */
    public function formatCliSummary(string $warehouseId): string
    {
        $report = $this->buildReport($warehouseId);
        $robotCount = Robot::query()->where('warehouse_id', $warehouseId)->count();

        return sprintf(
            'Warehouse=%s | Robots=%d | Missions=%d | CompletionPermil=%d | FallbackPermil=%d | Zones=%d | Waves=%d (Orders=%d) | ROI_Proposals=%d (NegNPV=%d) | Audit=%s',
            $warehouseId,
            $robotCount,
            (int) $report['kpi']['total_missions'],
            (int) $report['kpi']['completion_permil'],
            (int) $report['kpi']['fallback_permil'],
            count($report['zones_summary']),
            $report['wave_summary']['total_waves'],
            $report['wave_summary']['total_orders'],
            $report['roi_portfolio']['total_proposals'],
            $report['roi_portfolio']['negative_npv_count'],
            $report['audit_status']['passed'] ? 'PASS' : 'FAIL',
        );
    }
}
