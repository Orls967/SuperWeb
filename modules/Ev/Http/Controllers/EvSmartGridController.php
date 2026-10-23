<?php

declare(strict_types=1);

namespace Modules\Ev\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Ev\Application\Actions\DischargeV2GGridFeedInCreditAction;
use Modules\Ev\Application\Actions\SettleTimeOfUseChargingSessionAction;
use Modules\Ev\Application\Actions\StartOcppSmartChargingSessionAction;
use Modules\Ev\Domain\Models\EvBatteryPassport;
use Modules\Ev\Domain\Models\EvCharger;
use Modules\Ev\Domain\Models\EvSession;
use Modules\Ev\Domain\Models\EvStation;

class EvSmartGridController extends Controller
{
    public function ocppSessions(Request $request): View
    {
        $sessions = EvSession::with('charger')->latest()->paginate(15);
        $chargers = EvCharger::all();

        return view('ev::ev.ocpp_sessions', compact('sessions', 'chargers'));
    }

    public function touTariffs(Request $request): View
    {
        $stations = EvStation::with('chargers')->get();

        return view('ev::ev.tou_tariffs', compact('stations'));
    }

    public function v2gFeedIn(Request $request): View
    {
        $stations = EvStation::all();

        return view('ev::ev.v2g_feedin', compact('stations'));
    }

    public function batteryPassport(Request $request): View
    {
        $blocks = EvBatteryPassport::latest('recorded_at')->paginate(20);

        return view('ev::ev.battery_passport', compact('blocks'));
    }

    public function startOcpp(Request $request, StartOcppSmartChargingSessionAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'charger_id' => 'required|integer',
            'vehicle_id' => 'required|integer',
            'deposit_idr' => 'nullable|integer|min:0',
            'tariff_idr' => 'nullable|integer|min:0',
        ]);

        $userId = auth()->id() ?? 1;
        $deposit = (int) ($validated['deposit_idr'] ?? 50000);
        $tariff = (int) ($validated['tariff_idr'] ?? 2450);

        $result = $action->handle(
            (int) $validated['charger_id'],
            (int) $validated['vehicle_id'],
            $userId,
            $deposit,
            $tariff
        );

        return redirect()->route('ev.smart-grid.ocpp-sessions')
            ->with('success', "Smart charging session {$result['session_code']} initiated successfully.");
    }

    public function settleTou(Request $request, SettleTimeOfUseChargingSessionAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'session_id' => 'required|integer',
            'energy_wh' => 'required|integer|min:1',
            'final_soh' => 'nullable|numeric|min:0|max:100',
            'peak_temp' => 'nullable|numeric',
        ]);

        $result = $action->handle(
            (int) $validated['session_id'],
            (int) $validated['energy_wh'],
            (float) ($validated['final_soh'] ?? 98.2),
            (float) ($validated['peak_temp'] ?? 34.0)
        );

        return redirect()->route('ev.smart-grid.ocpp-sessions')
            ->with('success', "Session {$result['session']->session_code} settled. Total IDR ".number_format($result['total_cost_idr']));
    }

    public function dischargeV2g(Request $request, DischargeV2GGridFeedInCreditAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'station_id' => 'required|integer',
            'vehicle_id' => 'required|integer',
            'discharged_wh' => 'required|integer|min:100',
            'rate_idr' => 'nullable|integer|min:0',
        ]);

        $userId = auth()->id() ?? 1;
        $rate = (int) ($validated['rate_idr'] ?? 2800);

        $result = $action->handle(
            (int) $validated['station_id'],
            (int) $validated['vehicle_id'],
            $userId,
            (int) $validated['discharged_wh'],
            $rate
        );

        return redirect()->route('ev.smart-grid.v2g-feedin')
            ->with('success', "V2G feed-in complete: {$result['discharged_wh']} Wh discharged, credit IDR ".number_format($result['credit_earned_idr']).' accrued.');
    }
}
