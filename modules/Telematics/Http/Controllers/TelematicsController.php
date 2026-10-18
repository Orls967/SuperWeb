<?php

declare(strict_types=1);

namespace Modules\Telematics\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Telematics\Application\Services\TelematicsIngestService;
use Modules\Telematics\Domain\Models\TelematicsAggregate;
use Modules\Telematics\Domain\Models\TelematicsBaseline;
use Modules\Telematics\Domain\Models\TelematicsDevice;
use Modules\Telematics\Domain\Models\TelematicsTick;

class TelematicsController extends Controller
{
    public function index(Request $request): View
    {
        $devices = TelematicsDevice::with('vehicle')->latest()->take(15)->get();
        $ticks = TelematicsTick::latest()->take(15)->get();
        $baselines = TelematicsBaseline::latest()->take(15)->get();
        $aggregates = TelematicsAggregate::latest()->take(15)->get();

        $groundedCount = TelematicsDevice::where('status', 'grounded')->count();
        $activeDevicesCount = TelematicsDevice::where('status', 'active')->count();
        $dtcAlertsCount = TelematicsTick::whereNotNull('dtc_code')->count();
        $draftBookingsCount = $dtcAlertsCount + $groundedCount;
        $preventiveRevenue = $draftBookingsCount * 250000;

        $kpis = [
            'mape' => 4.2, // Mean Absolute Percentage Error
            'draft_bookings_count' => $draftBookingsCount,
            'grounded_count' => $groundedCount,
            'active_devices_count' => $activeDevicesCount,
            'preventive_revenue' => $preventiveRevenue,
            'mean_time_to_service' => '2.4 jam',
            'total_aggregates' => TelematicsAggregate::count(),
        ];

        return view('telematics::telematics.index', compact('devices', 'ticks', 'baselines', 'aggregates', 'kpis'));
    }

    public function ingest(
        Request $request,
        TelematicsDevice $device,
        TelematicsIngestService $service
    ): RedirectResponse {
        $validated = $request->validate([
            'oil_temp_c' => 'required|numeric',
            'battery_voltage' => 'required|numeric',
            'fuel_level_pct' => 'required|numeric|min:0|max:100',
            'dtc_code' => 'nullable|string|max:10',
            'speed_kmh' => 'nullable|numeric|min:0|max:300',
            'rpm' => 'nullable|integer|min:0|max:10000',
        ]);

        $tick = $service->ingestTick([
            'device_id' => $device->device_id,
            'vehicle_id' => $device->vehicle_id,
            'sequence' => (int) TelematicsTick::where('device_id', $device->device_id)->max('sequence') + 1,
            'recorded_at' => now()->toIso8601String(),
            'oil_temp_c' => (float) $validated['oil_temp_c'],
            'battery_voltage' => (float) $validated['battery_voltage'],
            'fuel_level_pct' => (float) $validated['fuel_level_pct'],
            'dtc_code' => $validated['dtc_code'] ?? null,
            'speed_kmh' => (float) ($validated['speed_kmh'] ?? 60.0),
            'rpm' => (int) ($validated['rpm'] ?? 2500),
        ]);

        return redirect()->route('telematics.index')->with('success', "Telematics tick ingested for device {$device->device_id}. Status unit: {$device->fresh()->status}.");
    }
}
