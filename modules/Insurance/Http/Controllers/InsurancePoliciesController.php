<?php

declare(strict_types=1);

namespace Modules\Insurance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Insurance\Application\Services\InsurancePolicyService;
use Modules\Insurance\Domain\Models\InsuranceProduct;

class InsurancePoliciesController extends Controller
{
    public function issue(Request $request, InsurancePolicyService $policyService): JsonResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key') ?? $request->header('X-Idempotency-Key') ?? $request->input('idempotency_key');
        if (empty($idempotencyKey)) {
            return response()->json(['message' => 'Idempotency-Key header is required'], 422);
        }

        $validated = $request->validate([
            'product_id' => 'required|integer|exists:ins_products,id',
            'duration_days' => 'required|integer|min:1',
        ]);

        $product = InsuranceProduct::findOrFail($validated['product_id']);
        $userId = (int) (auth()->id() ?? 1);

        $policy = $policyService->issuePolicy(
            product: $product,
            userId: $userId,
            durationDays: (int) $validated['duration_days'],
            idempotencyKey: (string) $idempotencyKey
        );

        return response()->json([
            'message' => 'Policy issued successfully',
            'policy' => $policy,
        ], 201);
    }
}
