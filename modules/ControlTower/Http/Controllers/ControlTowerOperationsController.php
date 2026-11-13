<?php

declare(strict_types=1);

namespace Modules\ControlTower\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\ControlTower\Application\Actions\OverrideDemandForecastAction;
use Modules\ControlTower\Application\Actions\RunWhatIfTwinScenarioAction;
use Modules\ControlTower\Application\Actions\StartSopCycleAction;
use Modules\ControlTower\Application\Actions\TransitionSopCycleAction;
use Modules\ControlTower\Domain\Models\DemandForecast;
use Modules\ControlTower\Domain\Models\EchelonStock;
use Modules\ControlTower\Domain\Models\SopCycle;
use Modules\ControlTower\Http\Requests\OverrideForecastRequest;
use Modules\ControlTower\Http\Requests\RunTwinSimulationRequest;
use Modules\ControlTower\Http\Requests\StoreSopCycleRequest;
use Modules\ControlTower\Http\Requests\TransitionSopCycleRequest;
use Modules\ControlTower\Http\ViewModels\ControlTowerDashboardViewModel;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ControlTowerOperationsController extends Controller
{
    public function __construct(
        private readonly ControlTowerDashboardViewModel $vm,
    ) {}

    public function sop(Request $request): View
    {
        Gate::forUser($request->user())->authorize('viewAny', SopCycle::class);

        return view('control_tower::sop', [
            'cycles' => $this->vm->sopCycles(),
            'summary' => $this->vm->summaryMetrics(),
        ]);
    }

    public function storeSop(StoreSopCycleRequest $request, StartSopCycleAction $action): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('manageSop', SopCycle::class);

        $data = $request->validated();
        $action->execute(
            period: (string) $data['period'],
            scenarioType: (string) $data['scenario_type'],
            demandPlanQty: (int) $data['demand_plan_qty'],
            supplyPlanQty: (int) $data['supply_plan_qty'],
            capacityUtilizationBps: (int) $data['capacity_utilization_bps'],
            grossMarginEstIdr: (int) $data['gross_margin_est_idr'],
            cashImpactEstIdr: (int) $data['cash_impact_est_idr'],
            actorUserId: $request->user()?->id,
        );

        return redirect()->route('control_tower.sop.index')->with('status', 'Siklus S&OP berhasil dibuat.');
    }

    public function transitionSop(TransitionSopCycleRequest $request, int $cycle, TransitionSopCycleAction $action): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('manageSop', SopCycle::class);

        $sopCycle = SopCycle::query()->findOrFail($cycle);
        $data = $request->validated();
        $action->execute(
            cycle: $sopCycle,
            toStatus: (string) $data['to_status'],
            actorUserId: $request->user()?->id,
            notes: isset($data['notes']) ? (string) $data['notes'] : null,
        );

        return redirect()->route('control_tower.sop.index')->with('status', 'Status S&OP diperbarui.');
    }

    public function forecasting(Request $request): View
    {
        Gate::forUser($request->user())->authorize('viewAny', SopCycle::class);

        return view('control_tower::forecasting', [
            'forecasts' => $this->vm->forecasts(),
            'summary' => $this->vm->summaryMetrics(),
        ]);
    }

    public function overrideForecast(OverrideForecastRequest $request, OverrideDemandForecastAction $action): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('overrideForecast', SopCycle::class);

        $data = $request->validated();
        $forecast = DemandForecast::query()->findOrFail((int) $data['demand_forecast_id']);
        $action->execute(
            forecast: $forecast,
            newQty: (int) $data['new_qty'],
            reason: (string) $data['reason'],
            actorUserId: $request->user()?->id,
        );

        return redirect()->route('control_tower.forecasting.index')->with('status', 'Override forecast tercatat.');
    }

    public function twin(Request $request): View
    {
        Gate::forUser($request->user())->authorize('viewAny', SopCycle::class);

        return view('control_tower::twin', [
            'scenarios' => $this->vm->twinScenarios(),
            'summary' => $this->vm->summaryMetrics(),
        ]);
    }

    public function simulateTwin(RunTwinSimulationRequest $request, RunWhatIfTwinScenarioAction $action): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('runTwin', SopCycle::class);

        $data = $request->validated();
        $action->execute(
            name: (string) $data['name'],
            shockType: (string) $data['shock_type'],
            shockMagnitudeBps: (int) $data['shock_magnitude_bps'],
            durationDays: (int) $data['duration_days'],
            actorUserId: $request->user()?->id,
        );

        return redirect()->route('control_tower.twin.index')->with('status', 'Simulasi What-If Digital Twin selesai.');
    }

    public function kpi(Request $request): View
    {
        Gate::forUser($request->user())->authorize('viewAny', SopCycle::class);

        return view('control_tower::kpi', [
            'snapshots' => $this->vm->kpiSnapshots(),
            'summary' => $this->vm->summaryMetrics(),
        ]);
    }

    public function safetyStock(Request $request): View
    {
        Gate::forUser($request->user())->authorize('viewAny', SopCycle::class);

        return view('control_tower::safety_stock', [
            'policies' => $this->vm->safetyStockPolicies(),
            'summary' => $this->vm->summaryMetrics(),
        ]);
    }

    public function exportEchelonCsv(Request $request): StreamedResponse
    {
        Gate::forUser($request->user())->authorize('viewAny', SopCycle::class);

        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['item_code', 'echelon_node', 'on_hand_qty', 'in_transit_qty', 'reserved_qty', 'abc_class', 'xyz_class']);
            foreach (EchelonStock::query()->orderBy('item_code')->cursor() as $row) {
                fputcsv($out, [
                    $row->item_code,
                    $row->echelon_node,
                    $row->on_hand_qty,
                    $row->in_transit_qty,
                    $row->reserved_qty,
                    $row->abc_class,
                    $row->xyz_class,
                ]);
            }
            fclose($out);
        }, 'control-tower-echelon.csv');
    }
}
