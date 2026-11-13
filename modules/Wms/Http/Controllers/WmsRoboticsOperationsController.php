<?php

declare(strict_types=1);

namespace Modules\Wms\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Modules\Core\Contracts\ClockInterface;
use Modules\Wms\Application\Actions\EvaluateRoboticsRoiProposalAction;
use Modules\Wms\Application\Actions\PlanRoboticsWaveAction;
use Modules\Wms\Application\Services\RoboticsCsvExportService;
use Modules\Wms\Application\Services\RoboticsFleetKpiAggregator;
use Modules\Wms\Application\Services\RoboticsShiftHandoverReportService;
use Modules\Wms\Application\Services\RoboticsSimulationScenarioRunner;
use Modules\Wms\Application\Services\RoboticsSimulationTickHandler;
use Modules\Wms\Application\Services\RoboticsT1SimulationReportService;
use Modules\Wms\Application\Services\RoboticsWaveReconciliationService;
use Modules\Wms\Application\Services\SafetyIncidentLog;
use Modules\Wms\Domain\Algorithms\RobotMaintenanceWindowPlanner;
use Modules\Wms\Domain\Algorithms\WaveCutoffSlackCalculator;
use Modules\Wms\Http\Requests\ApproveRoboticsRoiRequest;
use Modules\Wms\Http\Requests\PlanRoboticsWaveRequest;
use Modules\Wms\Http\Requests\ReviewSafetyIncidentRequest;
use Modules\Wms\Models\Robot;
use Modules\Wms\Models\RobotChargeSchedule;
use Modules\Wms\Models\RoboticsRoiProposal;
use Modules\Wms\Models\RobotSafetyIncident;
use Modules\Wms\Models\RobotWave;
use Modules\Wms\Models\TrafficZone;
use Modules\Wms\Models\ZoneReservation;

/**
 * Controller tipis untuk halaman zona, wave, simulasi, pengisian daya, insiden keselamatan, dan ekspor CSV (Fase 279).
 */
final class WmsRoboticsOperationsController extends Controller
{
    public function zones(Request $request): View
    {
        $zoneFilter = (string) $request->query('zone_code', '');
        $zones = TrafficZone::query()
            ->when($zoneFilter !== '', static fn ($q) => $q->where('zone_code', 'like', "%{$zoneFilter}%"))
            ->orderBy('zone_code')
            ->paginate(20);

        $reservations = ZoneReservation::query()
            ->with(['trafficZone', 'mission'])
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        return view('wms::robotics.zones', compact('zones', 'reservations', 'zoneFilter'));
    }

    public function waves(
        Request $request,
        WaveCutoffSlackCalculator $slackCalculator,
        RoboticsWaveReconciliationService $reconciler,
        ClockInterface $clock,
    ): View {
        $carrierFilter = (string) $request->query('carrier_code', '');
        $waves = RobotWave::query()
            ->when($carrierFilter !== '', static fn ($q) => $q->where('carrier_code', $carrierFilter))
            ->orderByDesc('id')
            ->paginate(20);

        $waveItems = collect($waves->items());
        $slackInput = $waveItems->map(static fn (RobotWave $w): array => [
            'wave_id' => (int) $w->id,
            'carrier_code' => (string) $w->carrier_code,
            'cutoff_hhmm' => max(0, min(2359, (int) $w->cutoff_min)),
            'estimated_pick_min' => max(10, (int) $w->allocated_load),
        ])->values()->all();

        $currentHhmm = (int) $clock->now()->format('Hi');
        $cutoffSlacks = $slackInput !== [] ? $slackCalculator->evaluate($slackInput, $currentHhmm) : [];

        $firstWh = (string) (TrafficZone::query()->value('warehouse_id') ?? '');
        $reconciliation = $firstWh !== '' ? $reconciler->reconcileBatch($firstWh, 'T1', []) : null;

        return view('wms::robotics.waves', compact('waves', 'carrierFilter', 'cutoffSlacks', 'reconciliation'));
    }

    public function simulations(
        RoboticsSimulationTickHandler $handler,
        RoboticsSimulationScenarioRunner $scenarioRunner,
        RoboticsFleetKpiAggregator $kpiAggregator,
        RoboticsT1SimulationReportService $reportService,
        RoboticsShiftHandoverReportService $handoverService,
        ClockInterface $clock,
    ): View {
        $snapshot = $handler->handle($clock->now(), 1);
        $baselineScenario = $scenarioRunner->runScenario('baseline');
        $whId = (string) (TrafficZone::query()->value('warehouse_id') ?? '');
        $kpis = $kpiAggregator->summarize($whId);
        $t1Report = $whId !== '' ? $reportService->buildReport($whId) : [];
        $handover = $whId !== '' ? $handoverService->buildHandover($whId) : [];

        return view('wms::robotics.simulations', compact('snapshot', 'baselineScenario', 'kpis', 't1Report', 'handover'));
    }

    public function charging(RobotMaintenanceWindowPlanner $maintenancePlanner): View
    {
        $schedules = RobotChargeSchedule::query()
            ->with('robot')
            ->orderBy('window_start_min')
            ->paginate(20);

        $robots = Robot::query()->orderBy('id')->get();
        $maintenanceCandidates = $robots->map(static fn (Robot $r, int $idx): array => [
            'robot_id' => (int) $r->id,
            'health_score' => (int) ($r->health_score ?? 800),
            'preferred_start_min' => ($idx * 30) % 240,
            'duration_min' => 30,
        ])->values()->all();

        $maintenancePlan = $robots->isNotEmpty()
            ? $maintenancePlanner->plan($maintenanceCandidates, $robots->count())
            : ['scheduled' => [], 'deferred_robot_ids' => [], 'max_concurrent_allowed' => 1];

        return view('wms::robotics.charging', compact('schedules', 'maintenancePlan'));
    }

    public function incidents(): View
    {
        $incidents = RobotSafetyIncident::query()
            ->orderByDesc('recorded_at')
            ->paginate(20);

        return view('wms::robotics.incidents', compact('incidents'));
    }

    public function planWave(PlanRoboticsWaveRequest $request, PlanRoboticsWaveAction $action): RedirectResponse
    {
        $data = $request->validated();
        $action->execute(
            (string) $data['warehouse_id'],
            (int) $data['capacity'],
            [
                ['id' => "{$data['batch_code']}-1", 'size' => 40, 'carrier' => 'JNE', 'cutoff' => 1700],
                ['id' => "{$data['batch_code']}-2", 'size' => 30, 'carrier' => 'JNE', 'cutoff' => 1700],
            ],
            (string) $data['batch_code'],
        );

        return redirect()->route('wms.robotics.waves')->with('status', 'Wave robotika FFD berhasil disusun.');
    }

    public function approveRoi(ApproveRoboticsRoiRequest $request, int $proposal, EvaluateRoboticsRoiProposalAction $action): RedirectResponse
    {
        $model = RoboticsRoiProposal::query()->findOrFail($proposal);
        $data = $request->validated();
        $action->approveProposal($model, (int) $request->user()->id, (string) ($data['justification'] ?? ''));

        return redirect()->route('wms.robotics.roi')->with('status', 'Proposal ROI disetujui.');
    }

    public function reviewIncident(ReviewSafetyIncidentRequest $request, int $incident, SafetyIncidentLog $log): RedirectResponse
    {
        $model = RobotSafetyIncident::query()->findOrFail($incident);
        $data = $request->validated();
        $log->review($model, (int) $request->user()->id, (string) $data['decision_status']);

        return redirect()->route('wms.robotics.incidents')->with('status', 'Insiden keselamatan telah ditinjau.');
    }

    public function exportCsv(string $entity, RoboticsCsvExportService $exporter): Response
    {
        $csv = $exporter->exportEntity($entity);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"robotics-{$entity}.csv\"",
        ]);
    }
}
