<?php

declare(strict_types=1);

namespace Modules\Mining\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Mining\Application\Actions\CalculatePnbpRoyaltyFromVerifiedTonnageAction;
use Modules\Mining\Application\Actions\EnforceBlastRadiusSafetyAndGeotechLockAction;
use Modules\Mining\Application\Actions\PostReclamationGuaranteeBondAndAmortizeAction;
use Modules\Mining\Domain\Models\BlastSchedule;
use Modules\Mining\Domain\Models\MiningPit;
use Modules\Mining\Domain\Models\MiningSite;
use Modules\Mining\Domain\Models\ReclamationProvision;
use Modules\Mining\Domain\Models\RoyaltyCalculation;

class MiningRoyaltyReclamationSafetyController extends Controller
{
    public function pnbpRoyalty(Request $request): View
    {
        $sites = MiningSite::all();
        $royalties = RoyaltyCalculation::latest()->paginate(15);

        return view('mining::mining.pnbp_royalty', compact('sites', 'royalties'));
    }

    public function calcRoyalty(
        Request $request,
        CalculatePnbpRoyaltyFromVerifiedTonnageAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'site_id' => 'required|string',
            'period' => 'required|string|max:7',
            'total_production_ton' => 'required|numeric|min:1',
            'commodity_benchmark_price_idr' => 'required|integer|min:1000',
            'royalty_rate_percentage' => 'nullable|numeric|min:0',
        ]);

        $result = $action->handle(
            $validated['site_id'],
            $validated['period'],
            (float) $validated['total_production_ton'],
            (int) $validated['commodity_benchmark_price_idr'],
            (float) ($validated['royalty_rate_percentage'] ?? 10.00)
        );

        return redirect()->route('mining.operations.pnbp-royalty')
            ->with('success', "Minerba PNBP Royalty for {$result['calculation']->period} Accrued: IDR ".number_format($result['royalty_due_idr']).' (Rate: '.$result['royalty_rate_pct'].'%).');
    }

    public function reclamationBonds(Request $request): View
    {
        $sites = MiningSite::all();
        $provisions = ReclamationProvision::latest()->paginate(15);

        return view('mining::mining.reclamation_bonds', compact('sites', 'provisions'));
    }

    public function postBond(
        Request $request,
        PostReclamationGuaranteeBondAndAmortizeAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'site_id' => 'required|string',
            'plan_code' => 'required|string|max:32',
            'target_hectares' => 'required|numeric|min:0.1',
            'provision_accrued_idr' => 'required|integer|min:1000000',
            'verified_growth_ndvi' => 'nullable|numeric|min:0|max:1',
        ]);

        $result = $action->handle(
            $validated['site_id'],
            $validated['plan_code'],
            (float) $validated['target_hectares'],
            (int) $validated['provision_accrued_idr'],
            (float) ($validated['verified_growth_ndvi'] ?? 0.0)
        );

        return redirect()->route('mining.operations.reclamation')
            ->with('success', "Reclamation Plan {$result['plan_code']} Evaluated: Status {$result['status']} (Released: IDR ".number_format($result['provision_released_idr']).').');
    }

    public function blastSafety(Request $request): View
    {
        $pits = MiningPit::all();
        $schedules = BlastSchedule::latest()->paginate(15);

        return view('mining::mining.blast_safety', compact('pits', 'schedules'));
    }

    public function lockBlast(
        Request $request,
        EnforceBlastRadiusSafetyAndGeotechLockAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'pit_id' => 'required|string',
            'blast_code' => 'required|string|max:32',
            'blast_coord_lat' => 'required|numeric',
            'blast_coord_lng' => 'required|numeric',
            'holes_count' => 'required|integer|min:1',
            'explosives_kg' => 'required|numeric|min:1',
            'active_personnel_in_radius' => 'required|integer|min:0',
        ]);

        $result = $action->handle(
            $validated['pit_id'],
            $validated['blast_code'],
            (float) $validated['blast_coord_lat'],
            (float) $validated['blast_coord_lng'],
            (int) $validated['holes_count'],
            (float) $validated['explosives_kg'],
            (int) $validated['active_personnel_in_radius']
        );

        return redirect()->route('mining.operations.blast-safety')
            ->with($result['is_clear_for_detonation'] ? 'success' : 'error', "Blast {$result['blast_schedule']->blast_code}: {$result['safety_lock_reason']}");
    }
}
