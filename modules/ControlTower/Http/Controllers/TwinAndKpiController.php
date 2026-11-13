<?php

declare(strict_types=1);

namespace Modules\ControlTower\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\ControlTower\Application\Services\ControlTowerKpiDrilldownService;
use Modules\ControlTower\Application\Services\TwinWhatIfOrchestratorService;
use Modules\ControlTower\Domain\Models\SopCycle;
use Modules\ControlTower\Domain\Models\TwinScenario;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TwinAndKpiController extends Controller
{
    public function __construct(
        private readonly TwinWhatIfOrchestratorService $twinOrchestrator,
        private readonly ControlTowerKpiDrilldownService $kpiDrilldownService,
    ) {}

    public function runWhatIf(Request $request): JsonResponse|RedirectResponse
    {
        Gate::forUser($request->user())->authorize('runTwin', SopCycle::class);

        $allowedScenarios = array_merge(
            TwinWhatIfOrchestratorService::SUPPORTED_SCENARIOS,
            ['DEMAND_SURGE', 'PLANT_EXPANSION']
        );

        $validated = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'shock_type' => ['sometimes', 'string', Rule::in($allowedScenarios)],
            'scenario' => ['sometimes', 'string', Rule::in($allowedScenarios)],
            'shock_magnitude_bps' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'magnitude' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'duration_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'seed' => ['sometimes', 'integer', 'min:1'],
            'compare_with_id' => ['sometimes', 'nullable', 'integer', 'exists:sct_twin_scenarios,id'],
        ]);

        $shockType = (string) ($validated['shock_type'] ?? $validated['scenario'] ?? 'PORT_CLOSURE');
        $magnitudeBps = (int) ($validated['shock_magnitude_bps'] ?? $validated['magnitude'] ?? 2500);
        $durationDays = (int) ($validated['duration_days'] ?? $validated['days'] ?? 14);
        $seed = (int) ($validated['seed'] ?? 53001);

        $result = $this->twinOrchestrator->runScenario(
            shockType: $shockType,
            shockMagnitudeBps: $magnitudeBps,
            durationDays: $durationDays,
            name: isset($validated['name']) ? (string) $validated['name'] : null,
            actorUserId: $request->user()?->id,
            seed: $seed,
        );

        $comparison = null;
        if (! empty($validated['compare_with_id'])) {
            $comparison = $this->twinOrchestrator->compareScenarios(
                (int) $validated['compare_with_id'],
                $result['scenario_id'],
            );
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $result + ['comparison' => $comparison],
            ], 201);
        }

        return redirect()
            ->route('control_tower.twin.index')
            ->with('status', "Simulasi What-If {$result['scenario_code']} selesai (zero mutation verified).");
    }

    public function exportTwinCsv(Request $request): StreamedResponse
    {
        Gate::forUser($request->user())->authorize('viewAny', SopCycle::class);

        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, [
                'scenario_code',
                'name',
                'shock_type',
                'shock_magnitude_bps',
                'duration_days',
                'baseline_otif_bps',
                'simulated_otif_bps',
                'at_risk_orders_count',
                'estimated_margin_delta_idr',
                'status',
                'created_at',
            ]);

            foreach (TwinScenario::query()->orderBy('id')->cursor() as $row) {
                fputcsv($out, [
                    $row->scenario_code,
                    $row->name,
                    $row->shock_type,
                    $row->shock_magnitude_bps,
                    $row->duration_days,
                    $row->baseline_otif_bps,
                    $row->simulated_otif_bps,
                    $row->at_risk_orders_count,
                    $row->estimated_margin_delta_idr,
                    $row->status,
                    $row->created_at?->toIso8601String(),
                ]);
            }

            fclose($out);
        }, 'control-tower-twin-scenarios.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function kpiDrilldown(Request $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize('viewAny', SopCycle::class);

        $validated = $request->validate([
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ]);

        $data = $this->kpiDrilldownService->drilldown(
            fromDate: $validated['from'] ?? null,
            toDate: $validated['to'] ?? null,
        );

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function exportKpiCsv(Request $request): StreamedResponse
    {
        Gate::forUser($request->user())->authorize('viewAny', SopCycle::class);

        $validated = $request->validate([
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ]);

        $report = $this->kpiDrilldownService->drilldown(
            fromDate: $validated['from'] ?? null,
            toDate: $validated['to'] ?? null,
        );

        return response()->streamDownload(function () use ($report): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, [
                'order_ref',
                'customer_ref',
                'item_code',
                'ordered_qty',
                'delivered_qty',
                'due_date',
                'delivered_date',
                'status',
                'period_otif_permil',
                'period_fill_rate_permil',
            ]);

            $otifPermil = $report['metrics']['otif_permil'];
            $fillRatePermil = $report['metrics']['fill_rate_permil'];

            foreach ($report['drilldown']['lines'] as $line) {
                fputcsv($out, [
                    $line['order_ref'],
                    $line['customer_ref'],
                    $line['item_code'],
                    $line['ordered_qty'],
                    $line['delivered_qty'],
                    $line['due_date'],
                    $line['delivered_date'] ?? '',
                    $line['status'],
                    $otifPermil,
                    $fillRatePermil,
                ]);
            }

            fclose($out);
        }, 'control-tower-kpi-drilldown.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
