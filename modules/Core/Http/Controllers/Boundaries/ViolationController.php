<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Boundaries;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Core\Application\Boundaries\BoundaryRemediationPlanner;
use Modules\Core\Application\Boundaries\BoundaryViolationAuditTrailService;
use Modules\Core\Application\Boundaries\TriageViolationAction;
use Modules\Core\Domain\Enums\ViolationCategory;
use Modules\Core\Domain\Enums\ViolationStatus;
use Modules\Core\Domain\Models\CoreBoundaryViolation;

final class ViolationController extends Controller
{
    public function __construct(
        private readonly BoundaryViolationAuditTrailService $auditTrail = new BoundaryViolationAuditTrailService,
        private readonly BoundaryRemediationPlanner $planner = new BoundaryRemediationPlanner,
        private readonly TriageViolationAction $triageAction = new TriageViolationAction,
    ) {}

    public function index(Request $request): View
    {
        $query = CoreBoundaryViolation::query();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('rule_id')) {
            $query->where('rule_id', $request->string('rule_id'));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        if ($request->filled('module')) {
            $module = $request->string('module')->toString();
            $query->where(function ($q) use ($module) {
                $q->where('client_module', $module)->orWhere('owner_module', $module);
            });
        }

        $violations = $query->orderBy('rule_id')
            ->orderBy('file_path')
            ->orderBy('line_number')
            ->paginate(30)
            ->withQueryString();

        $statusCounts = CoreBoundaryViolation::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        return view('core::boundaries.violations', [
            'violations' => $violations,
            'statuses' => ViolationStatus::values(),
            'categories' => ViolationCategory::values(),
            'statusCounts' => $statusCounts,
            'currentFilter' => [
                'status' => $request->string('status')->toString(),
                'rule_id' => $request->string('rule_id')->toString(),
                'category' => $request->string('category')->toString(),
                'module' => $request->string('module')->toString(),
            ],
        ]);
    }

    public function show(int $id): View
    {
        $violation = CoreBoundaryViolation::with('transitions')->findOrFail($id);
        $timelineData = $this->auditTrail->getTimeline($id);
        $remediationPlan = $this->planner->plan($violation);

        return view('core::boundaries.violation_show', [
            'violation' => $violation,
            'transitions' => $violation->transitions,
            'timelineData' => $timelineData,
            'remediationPlan' => $remediationPlan,
        ]);
    }

    /**
     * API JSON endpoint for violation forensic timeline.
     */
    public function timeline(int $id): JsonResponse
    {
        $timelineData = $this->auditTrail->getTimeline($id);

        return response()->json($timelineData);
    }

    /**
     * API JSON endpoint for violation refactoring recipe plan.
     */
    public function remediation(int $id): JsonResponse
    {
        $violation = CoreBoundaryViolation::findOrFail($id);
        $plan = $this->planner->plan($violation);

        return response()->json($plan);
    }

    /**
     * Bulk triage all detected violations into triaged state.
     */
    public function bulkTriage(Request $request): RedirectResponse
    {
        $actorId = (string) ($request->user()?->id ?? 'system:architect');
        $notes = $request->string('notes', 'Batch triage executed by architect')->toString();

        $detected = CoreBoundaryViolation::where('status', 'detected')->get();
        $triagedCount = 0;

        foreach ($detected as $v) {
            $this->triageAction->execute($v, $actorId, $notes);
            $triagedCount++;
        }

        return redirect()->route('admin.boundaries.violations')
            ->with('status', "Berhasil melakukan triage pada {$triagedCount} pelanggaran baru.");
    }
}
