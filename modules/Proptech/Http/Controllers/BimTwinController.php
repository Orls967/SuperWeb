<?php

declare(strict_types=1);

namespace Modules\Proptech\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Proptech\Application\Services\BimTwinService;
use Modules\Proptech\Domain\Models\Bim\BimModel;
use Modules\Proptech\Domain\Models\Bim\TwinComponent;
use Modules\Proptech\Domain\Models\Bim\TwinIssue;

class BimTwinController extends Controller
{
    public function index(Request $request): View
    {
        $models = BimModel::with('components.issues')->latest('version')->get();
        $components = TwinComponent::with(['bimModel', 'issues'])->latest()->take(20)->get();
        $issues = TwinIssue::with('component')->latest()->take(10)->get();

        $totalComponents = TwinComponent::count();
        $installedComponents = TwinComponent::where('status', 'INSTALLED')->orWhere('status', 'OPERATIONAL')->count();
        $avgProgress = $totalComponents > 0 ? (float) round(TwinComponent::avg('completion_pct'), 1) : 0.0;

        $simulationResult = session('simulation_result');

        return view('proptech::bim.index', compact(
            'models',
            'components',
            'issues',
            'totalComponents',
            'installedComponents',
            'avgProgress',
            'simulationResult'
        ));
    }

    public function simulate(
        Request $request,
        BimModel $model,
        BimTwinService $service
    ): RedirectResponse {
        $validated = $request->validate([
            'ambient_temp_c' => 'nullable|numeric|min:15|max:50',
        ]);

        $ambientTempC = isset($validated['ambient_temp_c']) ? (float) $validated['ambient_temp_c'] : 32.0;

        $result = $service->runTwinAirflowSimulation($model, $ambientTempC);

        return redirect()->route('proptech.bim.index')
            ->with('success', "Simulasi digital twin untuk {$model->name} berhasil dijalankan tanpa memutasi basis data.")
            ->with('simulation_result', $result);
    }

    public function capitalize(
        Request $request,
        BimTwinService $service
    ): RedirectResponse {
        $validated = $request->validate([
            'wbs_node_code' => 'required|string|max:32',
            'milestone_value_idr' => 'required|integer|min:1000',
        ]);

        try {
            $service->capitalizeBimMilestoneProgress(
                $validated['wbs_node_code'],
                (int) $validated['milestone_value_idr']
            );

            return redirect()->route('proptech.bim.index')
                ->with('success', "Kapitalisasi CIP milestone WBS {$validated['wbs_node_code']} ke Fixed Assets berhasil diposting ke ledger.");
        } catch (\Throwable $e) {
            return redirect()->route('proptech.bim.index')
                ->with('error', $e->getMessage());
        }
    }
}
