<?php

declare(strict_types=1);

namespace Modules\Syariah\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Syariah\Application\Services\SocialImpactScoringService;
use Modules\Syariah\Domain\Models\SybSocialImpactMetric;

class SocialImpactController extends Controller
{
    public function __construct(
        protected SocialImpactScoringService $impactService
    ) {}

    public function index(): View
    {
        $metrics = SybSocialImpactMetric::orderBy('period_year', 'desc')->get();

        return view('syariah::social.index', [
            'metrics' => $metrics,
        ]);
    }

    public function calculate(Request $request): JsonResponse
    {
        $year = (int) ($request->input('period_year', 2026));
        $impact = $this->impactService->calculateAnnualImpact($year);

        return response()->json([
            'message' => 'Indeks dampak sosial pemberdayaan ekonomi syariah berhasil dikalkulasi.',
            'impact' => $impact,
        ], 200);
    }

    public function graduationsIndex(): View
    {
        $graduations = \Modules\Syariah\Domain\Models\SybMustahikGraduation::orderBy('id', 'desc')->paginate(15);

        return view('syariah::social.graduation', [
            'graduations' => $graduations,
        ]);
    }

    public function storeGraduation(
        Request $request,
        \Modules\Syariah\Application\Services\MustahikGraduationTracker $tracker
    ): JsonResponse {
        $validated = $request->validate([
            'party_ref' => ['required', 'string', 'max:64'],
            'current_stage' => ['required', 'string', 'max:32'],
            'target_stage' => ['required', 'string', 'max:32'],
            'omzet_baseline_idr' => ['required', 'integer', 'min:10000000'],
            'omzet_current_idr' => ['required', 'integer', 'min:10000000'],
        ]);

        $grad = $tracker->initiateAssessment(
            partyRef: $validated['party_ref'],
            currentStage: $validated['current_stage'],
            targetStage: $validated['target_stage'],
            omzetBaselineIdr: $validated['omzet_baseline_idr'],
            omzetCurrentIdr: $validated['omzet_current_idr'],
            fieldOfficerUserId: (string) auth()->id()
        );

        return response()->json([
            'message' => 'Permohonan asesmen graduasi berhasil didaftarkan.',
            'graduation' => $grad,
        ], 201);
    }

    public function verifyGraduation(
        Request $request,
        int $id,
        \Modules\Syariah\Application\Services\MustahikGraduationTracker $tracker
    ): JsonResponse {
        $grad = \Modules\Syariah\Domain\Models\SybMustahikGraduation::findOrFail($id);
        $result = $tracker->verifyGraduation($grad->graduation_code, (string) auth()->id());

        return response()->json([
            'message' => 'Verifikasi supervisi amil selesai diproses.',
            'graduation' => $result,
        ], 200);
    }
}
