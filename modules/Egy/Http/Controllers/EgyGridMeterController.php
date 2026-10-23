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

class EgyGridMeterController extends Controller
{
    public function index(): View
    {
        $nodes = GridNode::query()->latest()->take(15)->get();
        $meters = SmartMeter::query()->latest()->take(15)->get();

        return view('egy::grid.index', compact('nodes', 'meters'));
    }

    public function meters(): View
    {
        $meters = SmartMeter::query()->latest()->paginate(20);
        $recentReadings = MeterReading::query()->latest('reading_timestamp')->take(20)->get();

        return view('egy::grid.meters', compact('meters', 'recentReadings'));
    }

    public function ingestTelemetry(Request $request, IngestSmartGridMeterTelemetryAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'meter_number' => ['required', 'string'],
            'kwh_consumed' => ['required', 'integer'],
            'peak_demand_kw' => ['nullable', 'integer'],
            'power_factor' => ['nullable', 'integer'],
        ]);

        $reading = $action->execute($validated);

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
