<?php

declare(strict_types=1);

namespace Modules\Hospital\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Hospital\Application\Services\ClinicalTrialAndResearchService;
use Modules\Hospital\Domain\Models\ClinicalTrial;
use Modules\Hospital\Domain\Models\HospitalBed;
use Modules\Hospital\Domain\Models\Patient;

class HospitalController extends Controller
{
    public function index(Request $request): View
    {
        $patients = Patient::latest()->take(10)->get();
        $beds = HospitalBed::latest()->take(10)->get();
        $trials = ClinicalTrial::with('subjects')->latest()->take(10)->get();

        return view('hospital::hospital.index', compact('patients', 'beds', 'trials'));
    }

    public function billTrialMilestone(
        Request $request,
        ClinicalTrial $trial,
        ClinicalTrialAndResearchService $service
    ): RedirectResponse {
        $validated = $request->validate([
            'milestone_name' => 'required|string|max:100',
            'amount_idr' => 'required|integer|min:1000',
        ]);

        $service->billSponsorMilestone($trial, $validated['milestone_name'], (int) $validated['amount_idr']);

        return redirect()->route('hospital.index')->with('success', "Milestone {$validated['milestone_name']} successfully billed.");
    }
}
