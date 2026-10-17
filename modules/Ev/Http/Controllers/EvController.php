<?php

declare(strict_types=1);

namespace Modules\Ev\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Ev\Application\Services\EvChargingService;
use Modules\Ev\Domain\Models\EvCharger;
use Modules\Ev\Domain\Models\EvSession;
use Modules\Ev\Domain\Models\EvStation;

class EvController extends Controller
{
    public function index(Request $request): View
    {
        $stations = EvStation::with('chargers')->latest()->take(10)->get();
        $chargers = EvCharger::with('station')->latest()->take(10)->get();
        $sessions = EvSession::with(['charger', 'charger.station'])->latest()->take(10)->get();

        return view('ev::ev.index', compact('stations', 'chargers', 'sessions'));
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
}
