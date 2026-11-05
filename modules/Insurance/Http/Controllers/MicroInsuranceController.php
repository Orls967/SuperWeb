<?php

declare(strict_types=1);

namespace Modules\Insurance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Insurance\Application\Actions\IssueMicroPolicyAction;
use Modules\Insurance\Application\Services\MicroInsuranceBatchService;
use Modules\Insurance\Domain\Models\MicroInsurancePolicy;

final class MicroInsuranceController extends Controller
{
    public function index(): View
    {
        $policies = MicroInsurancePolicy::latest()->paginate(25);

        return view('insurance::micro.index', compact('policies'));
    }

    public function analytics(MicroInsuranceBatchService $service): View
    {
        $analytics = $service->getMicroPortfolioAnalytics();

        return view('insurance::micro.portfolio_analytics', compact('analytics'));
    }

    public function store(Request $request, IssueMicroPolicyAction $action): JsonResponse
    {
        $validated = $request->validate([
            'policy_number' => 'required|string|max:60|unique:ins_micro_policies,policy_number',
            'member_party_id' => 'required|uuid',
            'product_type' => 'required|string|in:TRANSIT_DAILY,GADGET_MICRO,RIDER_PROTECT',
            'daily_premium_idr' => 'required|integer|min:1000|max:100000',
            'sum_insured_idr' => 'required|integer|min:100000',
            'duration_days' => 'nullable|integer|min:1|max:30',
        ]);

        $actorId = (string) ($request->user()?->id ?? 1);
        $idempotencyKey = $request->header('X-Idempotency-Key') ?? ('micro:iss:'.$validated['policy_number']);

        $res = $action->execute(
            policyNumber: $validated['policy_number'],
            memberPartyId: $validated['member_party_id'],
            productType: $validated['product_type'],
            dailyPremiumIdr: (int) $validated['daily_premium_idr'],
            sumInsuredIdr: (int) $validated['sum_insured_idr'],
            durationDays: (int) ($validated['duration_days'] ?? 1),
            actorId: $actorId,
            idempotencyKey: $idempotencyKey,
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Polis mikro instan berhasil diterbitkan.',
            'policy' => $res,
        ], 201);
    }
}
