<?php

declare(strict_types=1);

namespace Modules\Insurance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Insurance\Application\Actions\IssueAgriParametricPolicyAction;
use Modules\Insurance\Application\Actions\SettleAgriClaimWithOffsetAction;
use Modules\Insurance\Application\Services\AgriParametricOracleService;
use Modules\Insurance\Domain\Models\AgriInsuranceClaim;
use Modules\Insurance\Domain\Models\AgriInsurancePolicy;

final class AgriInsuranceController extends Controller
{
    public function index(): View
    {
        $policies = AgriInsurancePolicy::with('claims')->latest()->paginate(20);

        return view('insurance::agri.index', compact('policies'));
    }

    public function show(int $id): View
    {
        $policy = AgriInsurancePolicy::with('claims')->findOrFail($id);

        return view('insurance::agri.show', compact('policy'));
    }

    public function weatherStations(AgriParametricOracleService $service): View
    {
        $feeds = $service->fetchWeatherStationFeeds();

        return view('insurance::agri.weather_stations', compact('feeds'));
    }

    public function claims(): View
    {
        $claims = AgriInsuranceClaim::with('policy')->latest()->paginate(20);

        return view('insurance::agri.claims', compact('claims'));
    }

    public function store(Request $request, IssueAgriParametricPolicyAction $action): JsonResponse
    {
        $validated = $request->validate([
            'policy_number' => 'required|string|max:60|unique:ins_agri_policies,policy_number',
            'farmer_party_id' => 'required|uuid',
            'commodity' => 'required|string|in:PADI,JAGUNG,KELAPA_SAWIT,KEDELAI',
            'farm_area_ha' => 'required|integer|min:1',
            'sum_insured_idr' => 'required|integer|min:1000000',
            'premium_annual_idr' => 'required|integer|min:50000',
            'index_type' => 'required|string|in:RAINFALL,DROUGHT,NDVI_INDEX',
            'trigger_threshold_val' => 'required|integer|min:1',
        ]);

        $actorId = (string) ($request->user()?->id ?? 1);
        $idempotencyKey = $request->header('X-Idempotency-Key') ?? ('agri:iss:'.$validated['policy_number']);

        $res = $action->execute(
            policyNumber: $validated['policy_number'],
            farmerPartyId: $validated['farmer_party_id'],
            commodity: $validated['commodity'],
            farmAreaHa: (int) $validated['farm_area_ha'],
            sumInsuredIdr: (int) $validated['sum_insured_idr'],
            premiumAnnualIdr: (int) $validated['premium_annual_idr'],
            indexType: $validated['index_type'],
            triggerThresholdVal: (int) $validated['trigger_threshold_val'],
            actorId: $actorId,
            idempotencyKey: $idempotencyKey,
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Polis asuransi pertanian parametrik berhasil diterbitkan.',
            'policy' => $res,
        ], 201);
    }

    public function settle(Request $request, SettleAgriClaimWithOffsetAction $action): JsonResponse
    {
        $validated = $request->validate([
            'policy_number' => 'required|string|exists:ins_agri_policies,policy_number',
            'claim_number' => 'required|string|max:60|unique:ins_agri_claims,claim_number',
            'actual_index_val' => 'required|integer|min:0',
        ]);

        $actorId = (string) ($request->user()?->id ?? 1);
        $idempotencyKey = $request->header('X-Idempotency-Key') ?? ('agri:claim:'.$validated['claim_number']);

        $res = $action->execute(
            policyNumber: $validated['policy_number'],
            claimNumber: $validated['claim_number'],
            actualIndexVal: (int) $validated['actual_index_val'],
            actorId: $actorId,
            idempotencyKey: $idempotencyKey,
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Klaim asuransi tani berhasil diselesaikan dan dipotong dari cicilan pinjaman petani.',
            'claim' => $res,
        ], 201);
    }
}
