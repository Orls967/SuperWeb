<?php

declare(strict_types=1);

namespace Modules\Egy\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Egy\Application\Services\ConsolidatedUtilityBillingService;
use Modules\Egy\Domain\Models\UtilityConsolidatedInvoice;

class EgyInvoiceApiController extends Controller
{
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $invoice = UtilityConsolidatedInvoice::with('lines')->findOrFail($id);

        $isPrivileged = $user->hasAnyRbacRole(['admin', 'energy_manager', 'grid_operator', 'energy_auditor', 'genco_trader']);
        $isOwnTenant = isset($user->property_id) && $user->property_id === $invoice->property_id;

        if (! $isPrivileged && ! $isOwnTenant) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json($invoice, 200);
    }

    public function calculate(Request $request, ConsolidatedUtilityBillingService $billing): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $user->hasAnyRbacRole(['admin', 'energy_manager', 'genco_trader'])) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'property_id' => ['required', 'string'],
            'billing_period' => ['required', 'string'],
        ]);

        $invoice = $billing->generateMonthlyInvoice($validated['property_id'], $validated['billing_period']);

        return response()->json($invoice, 200);
    }

    public function issue(Request $request, string $id, ConsolidatedUtilityBillingService $billing): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $user->hasAnyRbacRole(['admin', 'energy_manager', 'genco_trader'])) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $invoice = $billing->issueInvoice($id);

        return response()->json($invoice, 200);
    }
}
