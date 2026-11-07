<?php

declare(strict_types=1);

namespace Modules\Mining\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Mining\Application\Actions\DispatchHaulTruckAction;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningHaulCycle;
use Modules\Mining\Domain\Models\MiningPit;
use Modules\Mining\Domain\Models\MiningSite;

class MiningDispatchController extends Controller
{
    public function index(Request $request): View
    {
        $sites = MiningSite::all();
        $pits = MiningPit::all();
        $trucks = MiningEquipment::where('type', 'HAUL_TRUCK')->get();
        $shovels = MiningEquipment::where('type', 'EXCAVATOR')->get();

        $activeCycles = MiningHaulCycle::with(['truck', 'shovel', 'pit'])
            ->latest()
            ->paginate(15);

        // Simulator Match Factor
        $simTrucks = (int) ($request->query('sim_trucks', 12));
        $simShovels = (int) ($request->query('sim_shovels', 3));
        $simLoading = (int) ($request->query('sim_loading', 4));
        $simCycle = (int) ($request->query('sim_cycle', 36));

        $num = $simTrucks * $simLoading;
        $den = $simShovels * $simCycle;
        $mfRatio = $den > 0 ? round($num / $den, 2) : 1.0;
        $mfRec = $num < $den
            ? 'Undertrucked: Shovel banyak menganggur, disarankan tambah unit truk.'
            : ($num > $den ? 'Overtrucked: Truk mengantre di shovel, disarankan alihkan truk.' : 'Match Factor Optimal.');

        $cycles = $activeCycles;

        return view('mining::dispatch.index', compact(
            'sites',
            'pits',
            'trucks',
            'shovels',
            'cycles',
            'activeCycles',
            'simTrucks',
            'simShovels',
            'simLoading',
            'simCycle',
            'mfRatio',
            'mfRec'
        ));
    }

    public function assign(Request $request, DispatchHaulTruckAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'site_id' => 'required|string',
            'pit_id' => 'required|string',
            'truck_id' => 'required|string',
            'shift_date' => 'required|date',
            'shift_code' => 'required|string',
            'target_payload_kg' => 'required|integer|min:1000',
        ]);

        $cycle = $action->execute(array_merge($validated, [
            'dispatcher_id' => $request->user()?->id,
            'is_override' => false,
        ]));

        return redirect()->route('mining.dispatch.ui')
            ->with('success', "Truk berhasil ditugaskan ke siklus {$cycle->cycle_seq} pada Shovel.");
    }

    public function override(Request $request, DispatchHaulTruckAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'site_id' => 'required|string',
            'pit_id' => 'required|string',
            'truck_id' => 'required|string',
            'chosen_shovel_id' => 'required|string',
            'shift_date' => 'required|date',
            'shift_code' => 'required|string',
            'target_payload_kg' => 'required|integer|min:1000',
            'override_reason' => 'required|string|min:5',
        ]);

        $cycle = $action->execute(array_merge($validated, [
            'dispatcher_id' => $request->user()?->id,
            'is_override' => true,
        ]));

        return redirect()->route('mining.dispatch.ui')
            ->with('success', "Dispatcher override berhasil dicatat: {$cycle->override_reason}");
    }
}
