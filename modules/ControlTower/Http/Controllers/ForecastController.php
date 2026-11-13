<?php

declare(strict_types=1);

namespace Modules\ControlTower\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\ControlTower\Application\Actions\RunForecastModelSelectionAction;
use Modules\ControlTower\Application\Services\ForecastOverrideService;
use Modules\ControlTower\Domain\Models\DemandForecast;
use Modules\ControlTower\Domain\Models\ForecastOverride;
use Modules\ControlTower\Domain\Models\SopCycle;
use Modules\ControlTower\Http\Requests\StoreForecastOverrideRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ForecastController extends Controller
{
    public function index(Request $request): View
    {
        Gate::forUser($request->user())->authorize('viewAny', SopCycle::class);

        $forecasts = DemandForecast::query()->latest('id')->limit(30)->get();
        $overrides = ForecastOverride::query()->with('forecast')->latest('id')->limit(30)->get();

        $selectedId = $request->integer('forecast_id');
        $activeForecast = $selectedId > 0
            ? $forecasts->firstWhere('id', $selectedId)
            : ($forecasts->first(static fn (DemandForecast $fc): bool => is_array($fc->candidates_json) && $fc->candidates_json !== []) ?? $forecasts->first());

        $summary = [
            'total_forecasts' => DemandForecast::query()->count(),
            'overridden_count' => DemandForecast::query()->where('is_overridden', true)->count(),
            'pending_approvals' => ForecastOverride::query()->where('status', ForecastOverrideService::STATUS_PENDING_APPROVAL)->count(),
        ];

        return view('control_tower::forecast', [
            'forecasts' => $forecasts,
            'overrides' => $overrides,
            'activeForecast' => $activeForecast,
            'summary' => $summary,
        ]);
    }

    public function select(Request $request, RunForecastModelSelectionAction $action): JsonResponse|RedirectResponse
    {
        Gate::forUser($request->user())->authorize('overrideForecast', SopCycle::class);

        $validated = $request->validate([
            'item_code' => ['required', 'string', 'max:32'],
            'period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'history' => ['required'],
        ]);

        $history = $this->parseHistory($validated['history']);
        $result = $action->execute(
            itemCode: (string) $validated['item_code'],
            period: (string) $validated['period'],
            history: $history,
        );

        if ($request->expectsJson()) {
            return response()->json(['data' => $result], 201);
        }

        return redirect()
            ->route('control_tower.forecast.index', ['forecast_id' => $result['forecast']->id])
            ->with('status', "Model terbaik terpilih: {$result['selected_model']} (Forecast: {$result['forecast_qty']} unit).");
    }

    public function override(StoreForecastOverrideRequest $request, ForecastOverrideService $service): JsonResponse|RedirectResponse
    {
        Gate::forUser($request->user())->authorize('overrideForecast', SopCycle::class);

        $data = $request->validated();
        $forecast = DemandForecast::query()->findOrFail((int) $data['demand_forecast_id']);

        $override = $service->recordOverride(
            forecast: $forecast,
            newQty: (int) $data['new_qty'],
            reason: (string) $data['reason'],
            actor: $request->user(),
        );

        if ($request->expectsJson()) {
            return response()->json(['data' => $override], 201);
        }

        $msg = $override->status === ForecastOverrideService::STATUS_PENDING_APPROVAL
            ? 'Override deviasi >= 20% dikirim ke antrean persetujuan.'
            : 'Override forecast berhasil diterapkan.';

        return redirect()->route('control_tower.forecast.index')->with('status', $msg);
    }

    public function revert(Request $request, int $override, ForecastOverrideService $service): JsonResponse|RedirectResponse
    {
        Gate::forUser($request->user())->authorize('overrideForecast', SopCycle::class);

        $overrideModel = ForecastOverride::query()->findOrFail($override);
        $reverted = $service->revertOverride($overrideModel, $request->user());

        if ($request->expectsJson()) {
            return response()->json(['data' => $reverted]);
        }

        return redirect()->route('control_tower.forecast.index')->with('status', 'Forecast dikembalikan ke kuantitas baseline.');
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        Gate::forUser($request->user())->authorize('viewAny', SopCycle::class);

        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, [
                'forecast_code',
                'item_code',
                'period',
                'algorithm_model',
                'forecast_qty',
                'mae_scaled',
                'mape_percent',
                'is_overridden',
                'override_reason',
                'certificate_hash',
            ]);

            foreach (DemandForecast::query()->orderBy('item_code')->orderBy('period')->cursor() as $row) {
                fputcsv($out, [
                    $row->forecast_code,
                    $row->item_code,
                    $row->period,
                    $row->algorithm_model,
                    $row->forecast_qty,
                    $row->mae_scaled,
                    $row->mape_percent,
                    $row->is_overridden ? '1' : '0',
                    $row->override_reason,
                    $row->certificate_hash,
                ]);
            }

            fclose($out);
        }, 'control-tower-forecasts.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return list<int>
     */
    private function parseHistory(mixed $rawHistory): array
    {
        $items = is_string($rawHistory)
            ? array_values(array_filter(array_map('trim', explode(',', $rawHistory)), static fn (string $v): bool => $v !== ''))
            : (is_array($rawHistory) ? array_values($rawHistory) : []);

        if (count($items) < 3) {
            throw ValidationException::withMessages([
                'history' => ['Minimal 3 periode historis diperlukan (misal: 100,110,120).'],
            ]);
        }

        $parsed = [];
        foreach ($items as $value) {
            if (is_int($value)) {
                $intVal = $value;
            } elseif (is_string($value) && preg_match('/^\d+$/', $value)) {
                $intVal = (int) $value;
            } else {
                throw ValidationException::withMessages([
                    'history' => ['Seluruh nilai historis harus berupa integer non-negatif.'],
                ]);
            }

            if ($intVal < 0) {
                throw ValidationException::withMessages([
                    'history' => ['Nilai historis tidak boleh negatif.'],
                ]);
            }

            $parsed[] = $intVal;
        }

        return $parsed;
    }
}
