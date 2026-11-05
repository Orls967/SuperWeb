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
}
