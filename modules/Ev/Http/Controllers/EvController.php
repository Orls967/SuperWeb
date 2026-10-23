<?php

declare(strict_types=1);

namespace Modules\Ev\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Ev\Application\Services\EvChargingService;
use Modules\Ev\Domain\Models\EvBatteryPassport;
use Modules\Ev\Domain\Models\EvCharger;
use Modules\Ev\Domain\Models\EvSession;
use Modules\Ev\Domain\Models\EvStation;

class EvController extends Controller
{
    public function index(Request $request): View
    {
        $stations = EvStation::with('chargers')->latest()->take(10)->get();
        $chargers = EvCharger::with('station')->latest()->take(15)->get();
        $sessions = EvSession::with(['charger', 'charger.station', 'vehicle'])->latest()->take(15)->get();
        $passports = EvBatteryPassport::latest()->take(15)->get();

        $totalEnergyWh = (int) EvSession::where('status', 'completed')->sum('energy_wh');
        $totalRevenueIdr = (int) EvSession::where('status', 'completed')->sum('total_cost_idr');
        $totalGreenKmSaved = (float) EvSession::where('status', 'completed')->sum('green_km_saved_co2_kg');
        $avgSoh = (float) (EvBatteryPassport::avg('final_soh_pct') ?? 100.0);

        return view('ev::ev.index', compact(
            'stations',
            'chargers',
            'sessions',
            'passports',
            'totalEnergyWh',
            'totalRevenueIdr',
            'totalGreenKmSaved',
            'avgSoh'
        ));
    }

    public function reserve(
        Request $request,
        EvCharger $charger,
        EvChargingService $service
    ): RedirectResponse {
        $validated = $request->validate([
            'vehicle_id' => 'required|integer',
            'duration_minutes' => 'nullable|integer|min:15|max:120',
        ]);

        $duration = (int) ($validated['duration_minutes'] ?? 30);
        $user = $request->user();

        $session = $service->reserveSlot(
            chargerId: $charger->id,
            vehicleId: (int) $validated['vehicle_id'],
            userId: $user->id,
            startTime: Carbon::now(),
            durationMinutes: $duration
        );

        return redirect()->route('ev.index')->with('success', "Slot reserved on charger {$charger->charger_code}. Session code: {$session->session_code}.");
    }

    public function checkIn(
        Request $request,
        EvCharger $charger,
        EvChargingService $service
    ): RedirectResponse {
        $validated = $request->validate([
            'session_id' => 'required|integer',
            'qr_code' => 'required|string',
        ]);

        $session = EvSession::where('charger_id', $charger->id)->findOrFail((int) $validated['session_id']);

        try {
            $service->checkInQr($session, (string) $validated['qr_code']);

            return redirect()->route('ev.index')->with('success', "Check-in successful. Charging commenced for session {$session->session_code}.");
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('ev.index')->withErrors(['qr_code' => $e->getMessage()]);
        }
    }

    public function complete(
        Request $request,
        EvSession $session,
        EvChargingService $service
    ): RedirectResponse {
        $validated = $request->validate([
            'energy_wh' => 'required|integer|min:100',
            'peak_cell_temp_c' => 'nullable|numeric|min:20|max:80',
        ]);

        $energyWh = (int) $validated['energy_wh'];
        $temp = isset($validated['peak_cell_temp_c']) ? (float) $validated['peak_cell_temp_c'] : 35.0;

        $completed = $service->completeCharging($session, $energyWh, null, $temp);

        return redirect()->route('ev.index')->with('success', "Session {$completed->session_code} completed. Billed: Rp ".number_format($completed->total_cost_idr, 0, ',', '.'));
    }

    public function loadBalance(
        Request $request,
        EvStation $station,
        EvChargingService $service
    ): RedirectResponse {
        $result = $service->simulateGridLoadBalancing($station->id);

        $msg = "Grid load balancing executed for {$station->name}. Utilization: {$result['utilization_pct']}%. Deferred: {$result['deferred_sessions_count']} sessions.";

        return redirect()->route('ev.index')->with('success', $msg);
    }
}
