<?php

declare(strict_types=1);

namespace Modules\Wealth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Wealth\Application\Services\RoboAdvisorService;

class WealthRiskProfileController extends Controller
{
    public function submit(Request $request, RoboAdvisorService $service): JsonResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key') ?? $request->header('X-Idempotency-Key') ?? $request->input('idempotency_key');
        if (empty($idempotencyKey)) {
            return response()->json(['message' => 'Idempotency-Key header is required'], 422);
        }

        $validated = $request->validate([
            'answers' => 'required|array',
        ]);

        $userId = (int) (auth()->id() ?? 1);
        $profile = $service->submitRiskSurvey($userId, $validated['answers'], (string) $idempotencyKey);

        return response()->json([
            'message' => 'Risk survey submitted successfully',
            'profile' => $profile,
        ], 200);
    }
}
