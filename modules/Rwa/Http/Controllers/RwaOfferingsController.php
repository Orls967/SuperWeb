<?php

declare(strict_types=1);

namespace Modules\Rwa\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Rwa\Application\Services\RwaOfferingService;

class RwaOfferingsController extends Controller
{
    public function subscribe(Request $request, int|string $offeringId, RwaOfferingService $offeringService): JsonResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key') ?? $request->header('X-Idempotency-Key') ?? $request->input('idempotency_key');
        if (empty($idempotencyKey)) {
            return response()->json(['message' => 'Idempotency-Key header is required'], 422);
        }

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $userId = (int) (auth()->id() ?? 1);
        $order = $offeringService->placeOrder(
            offeringId: (int) $offeringId,
            investorId: $userId,
            quantity: (int) $validated['quantity'],
            idempotencyKey: (string) $idempotencyKey
        );

        return response()->json([
            'message' => 'Subscribed successfully',
            'order' => $order,
        ], 201);
    }
}
