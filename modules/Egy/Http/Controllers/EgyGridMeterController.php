<?php

declare(strict_types=1);

namespace Modules\Egy\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Egy\Application\Actions\DetectGridTransformerLoadAnomalyAction;
use Modules\Egy\Application\Actions\IngestSmartGridMeterTelemetryAction;
use Modules\Egy\Domain\Models\GridNode;
use Modules\Egy\Domain\Models\MeterReading;
use Modules\Egy\Domain\Models\SmartMeter;
use Modules\Egy\Domain\Models\UtilityConsolidatedInvoice;

class EgyGridMeterController extends Controller
{
    public function index(): View
    {
        $nodes = GridNode::query()->latest()->take(15)->get();
        $meters = SmartMeter::query()->latest()->take(15)->get();
        $generationAssets = \Modules\Egy\Domain\Models\GenerationAsset::orderBy('marginal_cost_idr')->get();

        $totalOnlineCapacityMw = (float) \Modules\Egy\Domain\Models\GenerationAsset::where('status', 'online')->orWhere('status', 'ONLINE')->sum('capacity_mw');
        if ($totalOnlineCapacityMw <= 0) {
            $totalOnlineCapacityMw = (float) \Modules\Egy\Domain\Models\GenerationAsset::where('status', 'online')->orWhere('status', 'ONLINE')->sum('installed_capacity_mw');
        }
        $totalCurrentLoadMw = (float) $nodes->sum('current_load_mw');
        $forecastLoadMw = $totalCurrentLoadMw > 0 ? $totalCurrentLoadMw * 1.15 : 120.0;
        $reserveMarginMw = max(0.0, $totalOnlineCapacityMw - $totalCurrentLoadMw);

        return view('egy::grid.index', compact(
            'nodes',
            'meters',
            'generationAssets',
            'totalOnlineCapacityMw',
            'totalCurrentLoadMw',
            'forecastLoadMw',
            'reserveMarginMw'
        ));
    }

    public function meters(): View
    {
        $meters = SmartMeter::query()->latest()->paginate(20);
        $recentReadings = MeterReading::query()->latest('reading_at')->take(20)->get();

        return view('egy::grid.meters', compact('meters', 'recentReadings'));
    }

    public function dispatchView(): View
    {
        $nodes = GridNode::query()->latest()->take(10)->get();

        return view('egy::grid.dispatch', compact('nodes'));
    }

    public function invoicesView(): View
    {
        $invoices = UtilityConsolidatedInvoice::with('lines')->latest()->paginate(15);

        return view('egy::grid.invoices', compact('invoices'));
    }

    public function ingestTelemetry(Request $request, IngestSmartGridMeterTelemetryAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'meter_id' => ['nullable', 'string'],
            'meter_number' => ['nullable', 'string'],
            'kwh_consumed' => ['required', 'numeric'],
            'peak_demand_kw' => ['nullable', 'numeric'],
            'power_factor' => ['nullable', 'numeric'],
        ]);

        $reading = $action->execute([
            'meter_id' => $validated['meter_id'] ?? null,
            'meter_number' => $validated['meter_number'] ?? null,
            'consumption_kwh' => (int) $validated['kwh_consumed'],
            'peak_kw' => (int) ($validated['peak_demand_kw'] ?? 0),
            'cumulative_wh' => (int) $validated['kwh_consumed'] * 1000,
            'delta_wh' => (int) $validated['kwh_consumed'] * 1000,
        ]);

        return redirect()->back()->with('success', "Telemetri meter dicatat [{$reading->kwh_consumed} kWh].");
    }

    public function scanAnomaly(string $nodeId, DetectGridTransformerLoadAnomalyAction $action): RedirectResponse
    {
        $result = $action->execute($nodeId);

        $statusMsg = $result['is_anomaly']
            ? "PERINGATAN: Anomali beban trafo terdeteksi pada node {$result['node_code']} ({$result['severity']}: {$result['load_ratio_percent']}%)!"
            : "Kondisi normal: Beban trafo node {$result['node_code']} berada pada {$result['load_ratio_percent']}%.";

        return redirect()->back()->with('status_report', $statusMsg);
    }
}
