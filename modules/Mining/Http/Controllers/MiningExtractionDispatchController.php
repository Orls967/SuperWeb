<?php

declare(strict_types=1);

namespace Modules\Mining\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Mining\Application\Actions\DispatchHaulTruckAndDetectFuelAnomalyAction;
use Modules\Mining\Application\Actions\PlanMineBlockExtractionAndStrippingRatioAction;
use Modules\Mining\Domain\Models\MiningDispatchRun;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningPit;
use Modules\Mining\Domain\Models\MiningSite;

class MiningExtractionDispatchController extends Controller
{
    public function blockPlanning(Request $request): View
    {
        $sites = MiningSite::all();
        $pits = MiningPit::all();

        return view('mining::mining.block_planning', compact('sites', 'pits'));
    }

    public function planBlock(
        Request $request,
        PlanMineBlockExtractionAndStrippingRatioAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'site_id' => 'required|string',
            'pit_id' => 'required|string',
            'ore_tonnage' => 'required|numeric|min:1',
            'waste_overburden_bcm' => 'required|numeric|min:0',
            'target_grade_ni_pct' => 'nullable|numeric|min:0',
        ]);

        $result = $action->handle(
            $validated['site_id'],
            $validated['pit_id'],
            (float) $validated['ore_tonnage'],
            (float) $validated['waste_overburden_bcm'],
            (float) ($validated['target_grade_ni_pct'] ?? 1.80)
        );

        return redirect()->route('mining.operations.block-planning')
            ->with('success', "Mine Block Planned: Stripping Ratio {$result['stripping_ratio']} (Viable: ".($result['is_economically_viable'] ? 'YES' : 'NO').') &ndash; Estimated Cost: IDR '.number_format($result['estimated_cost_idr']).'.');
    }

    public function haulDispatch(Request $request): View
    {
        $sites = MiningSite::all();
        $pits = MiningPit::all();
        $equipments = MiningEquipment::all();
        $runs = MiningDispatchRun::latest()->paginate(15);

        return view('mining::mining.haul_dispatch', compact('sites', 'pits', 'equipments', 'runs'));
    }

    public function dispatchTruck(
        Request $request,
        DispatchHaulTruckAndDetectFuelAnomalyAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'site_id' => 'required|string',
            'pit_id' => 'required|string',
            'equipment_id' => 'required|string',
            'shift_code' => 'required|string|max:32',
            'target_payload_ton' => 'required|numeric|min:1',
            'actual_payload_ton' => 'required|numeric|min:1',
            'fuel_consumed_liter' => 'required|numeric|min:0',
            'distance_km' => 'required|numeric|min:0.1',
        ]);

        $result = $action->handle(
            $validated['site_id'],
            $validated['pit_id'],
            $validated['equipment_id'],
            $validated['shift_code'],
            (float) $validated['target_payload_ton'],
            (float) $validated['actual_payload_ton'],
            (float) $validated['fuel_consumed_liter'],
            (float) $validated['distance_km']
        );

        $msg = "Haul Trip Recorded: Actual Payload {$result['dispatch_run']->actual_payload_ton} Ton (Variance: {$result['payload_variance_ton']} Ton).";
        if ($result['fuel_anomaly_detected']) {
            $msg .= ' [ANOMALY DETECTED: Specific Fuel Consumption High &ndash; Payment Held]';
        }

        return redirect()->route('mining.operations.haul-dispatch')
            ->with($result['fuel_anomaly_detected'] ? 'error' : 'success', $msg);
    }
}
