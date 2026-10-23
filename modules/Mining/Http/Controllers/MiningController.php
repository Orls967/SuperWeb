<?php

declare(strict_types=1);

namespace Modules\Mining\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Mining\Application\Services\MiningFleetDispatchService;
use Modules\Mining\Domain\Models\MiningDispatchRun;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningPit;
use Modules\Mining\Domain\Models\MiningSite;

class MiningController extends Controller
{
    public function index(Request $request): View
    {
        $sites = MiningSite::latest()->take(10)->get();
        $pits = MiningPit::latest()->take(10)->get();
        $equipment = MiningEquipment::latest()->take(10)->get();
        $dispatches = MiningDispatchRun::latest()->take(10)->get();

        return view('mining::mining.index', compact('sites', 'pits', 'equipment', 'dispatches'));
    }

    public function dispatch(
        Request $request,
        MiningPit $pit,
        MiningFleetDispatchService $service
    ): RedirectResponse {
        $validated = $request->validate([
            'equipment_id' => 'required|string',
            'shift_code' => 'required|string|max:20',
            'target_payload_ton' => 'required|numeric|min:1',
        ]);

        $service->dispatchEquipment(
            $pit->site_id,
            (string) $pit->id,
            $validated['equipment_id'],
            $validated['shift_code'],
            (float) $validated['target_payload_ton']
        );

        return redirect()->route('mining.index')->with('success', "Equipment dispatched to pit {$pit->pit_code} successfully.");
    }
}
