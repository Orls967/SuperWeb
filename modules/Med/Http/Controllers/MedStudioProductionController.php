<?php

declare(strict_types=1);

namespace Modules\Med\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Med\Application\Actions\GreenlightStudioProductionBudgetAction;
use Modules\Med\Domain\Models\MedProject;
use Modules\Med\Domain\Models\MedStudio;
use Modules\Med\Domain\Models\MedStudioBooking;

class MedStudioProductionController extends Controller
{
    public function index(): View
    {
        $studios = MedStudio::query()->latest()->take(15)->get();
        $bookings = MedStudioBooking::query()->latest()->take(15)->get();
        $projects = MedProject::query()->latest()->take(15)->get();

        return view('med::studios.index', compact('studios', 'bookings', 'projects'));
    }

    public function show(string $id): View
    {
        $project = MedProject::findOrFail($id);

        return view('med::studios.show', compact('project'));
    }

    public function greenlight(Request $request, string $id, GreenlightStudioProductionBudgetAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'budget_limit_minor' => ['required', 'integer', 'min:1000000'],
        ]);

        $project = $action->execute($id, (int) $validated['budget_limit_minor']);

        return redirect()->back()->with('success', "Produksi [{$project->title}] disetujui (Greenlit) dengan anggaran Rp ".number_format($project->budget_limit_minor, 0, ',', '.'));
    }
}
