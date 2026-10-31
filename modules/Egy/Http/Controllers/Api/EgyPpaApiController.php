<?php

declare(strict_types=1);

namespace Modules\Egy\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Egy\Application\Actions\SettlePowerPurchaseAgreementBillingAction;

class EgyPpaApiController extends Controller
{
    public function settle(Request $request, string $id, SettlePowerPurchaseAgreementBillingAction $action): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $user->hasAnyRbacRole(['admin', 'genco_trader', 'energy_manager'])) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $idempotencyKey = $request->header('Idempotency-Key');
        if (! $idempotencyKey) {
            return response()->json(['message' => 'Idempotency-Key header is required.'], 422);
        }

        $validated = $request->validate([
            'delivered_kwh' => ['required', 'integer', 'min:1'],
        ]);

        $result = $action->executeWithIntercompany($id, (int) $validated['delivered_kwh'], $idempotencyKey);

        return response()->json($result, 200);
    }
}
