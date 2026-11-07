<?php

declare(strict_types=1);

namespace Modules\Mining\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Mining\Application\Actions\OptimizeOreBlendingAction;
use Modules\Mining\Application\Actions\TrackMineReserveDepletionAction;
use Modules\Mining\Domain\Models\MiningAssayComparison;
use Modules\Mining\Domain\Models\MiningReconciliationRun;
use Modules\Mining\Domain\Models\MiningStockpile;

class MiningGradeControlController extends Controller
{
    public function index()
    {
        $runs = MiningReconciliationRun::latest()->take(10)->get();
        $stockpiles = MiningStockpile::all();
        $assays = MiningAssayComparison::latest()->take(10)->get();

        return view('mining::grade', [
            'runs' => $runs,
            'stockpiles' => $stockpiles,
            'assays' => $assays,
        ]);
    }

    /**
     * Endpoint simulasi & eksekusi optimasi blending batubara / bijih (D94-4).
     */
    public function blend(Request $request, OptimizeOreBlendingAction $action)
    {
        $validated = $request->validate([
            'target_tonnage' => 'required|integer|min:100',
            'target_gar_kcal' => 'required|integer|min:3000',
            'max_ash_pct' => 'required|numeric|min:1',
            'max_sulfur_pct' => 'required|numeric|min:0.1',
            'candidate_stockpile_ids' => 'required|array|min:1',
        ]);

        $result = $action->execute($validated);

        return redirect()->back()->with('success', "Blending selesai: {$result['recommendation']}");
    }

    /**
     * Endpoint pelacakan deplesi cadangan tambang JORC/KCMI (D94-4).
     */
    public function trackDepletion(Request $request, TrackMineReserveDepletionAction $action)
    {
        $validated = $request->validate([
            'site_id' => 'required|uuid',
            'pit_code' => 'required|string|max:32',
            'initial_proved_reserves_tons' => 'required|integer|min:0',
            'initial_probable_reserves_tons' => 'required|integer|min:0',
            'mined_production_tons' => 'required|integer|min:0',
            'exploration_conversion_tons' => 'nullable|integer|min:0',
            'actual_overburden_bcm' => 'required|integer|min:0',
            'planned_life_of_mine_sr' => 'required|numeric|min:0.1',
            'stripping_cost_per_bcm_idr' => 'required|integer|min:100',
        ]);

        $result = $action->execute($validated);

        return redirect()->back()->with('success', "Deplesi cadangan pit {$result['pit_code']}: {$result['exhaustion_risk_verdict']}");
    }
}
