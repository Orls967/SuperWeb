<?php

declare(strict_types=1);

namespace Modules\Edu\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Edu\Application\Actions\DisburseResearchGrantMilestoneAction;
use Modules\Edu\Domain\Models\EduResearchGrant;
use Modules\Edu\Domain\Models\EduScholarshipEscrow;

class EduResearchGrantController extends Controller
{
    public function index(): View
    {
        $grants = EduResearchGrant::query()->with('milestones')->latest()->take(15)->get();
        $escrows = EduScholarshipEscrow::query()->latest()->take(10)->get();

        return view('edu::grants.index', compact('grants', 'escrows'));
    }

    public function show(string $id): View
    {
        $grant = EduResearchGrant::with('milestones')->findOrFail($id);

        return view('edu::grants.show', compact('grant'));
    }

    public function disburseMilestone(string $milestoneId, DisburseResearchGrantMilestoneAction $action): RedirectResponse
    {
        $milestone = $action->execute($milestoneId);

        return redirect()->back()->with('success', "Milestone [{$milestone->milestone_code}] berhasil dicairkan.");
    }
}
