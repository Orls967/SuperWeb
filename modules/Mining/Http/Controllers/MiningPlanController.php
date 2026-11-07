<?php

declare(strict_types=1);

namespace Modules\Mining\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Mining\Application\Actions\PlanMineBlockAction;
use Modules\Mining\Application\Actions\RePlanShiftTargetsAction;
use Modules\Mining\Domain\Models\MiningPit;
use Modules\Mining\Domain\Models\MiningPlan;
use Modules\Mining\Domain\Models\MiningSite;

class MiningPlanController extends Controller
{
    public function index(Request $request): View
    {
        $sites = MiningSite::all();
        $pits = MiningPit::all();
        $plans = MiningPlan::with(['site', 'shifts'])->latest()->paginate(10);

        return view('mining::plans.index', compact('sites', 'pits', 'plans'));
    }

    public function store(Request $request, PlanMineBlockAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'site_id' => 'required|string',
            'pit_id' => 'required|string',
            'period_month' => 'required|string|regex:/^\d{4}-\d{2}$/',
            'commodity' => 'required|string',
            'target_tonnage_kg' => 'required|integer|min:1000',
            'target_grade_ppm' => 'required|integer|min:0',
        ]);

        $plan = $action->execute(array_merge($validated, [
            'user_id' => $request->user()?->id,
        ]));

        return redirect()->route('mining.plans')
            ->with('success', "Rencana tambang {$plan->plan_code} berhasil dibuat dan di-time-phased ke shift.");
    }

    public function replan(Request $request, MiningPlan $plan, RePlanShiftTargetsAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'additional_rain_hours' => 'nullable|integer|min:0|max:12',
        ]);

        $result = $action->execute([
            'mine_plan_id' => $plan->id,
            'additional_rain_hours' => $validated['additional_rain_hours'] ?? null,
        ]);

        return redirect()->route('mining.plans')
            ->with('success', "Penyesuaian target shift selesai: {$result['shifts_updated']} shift diperbarui, {$result['total_lost_hours']} jam hujan terhitung.");
    }
}
