<?php

declare(strict_types=1);

namespace Modules\Insurance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Insurance\Application\Actions\ClaimLifeBenefitAction;
use Modules\Insurance\Application\Actions\IssueLifePolicyAction;
use Modules\Insurance\Application\Actions\SurrenderLifePolicyAction;
use Modules\Insurance\Application\Actions\UnderwriteMedicalApplicationAction;
use Modules\Insurance\Application\Services\LifeHealthReportService;
use Modules\Insurance\Domain\Models\LifePolicy;

final class LifeInsuranceController extends Controller
{
    public function index(): View
    {
        $policies = LifePolicy::with('beneficiaries', 'unitHoldings.fund')->latest()->paginate(20);

        return view('insurance::life.index', compact('policies'));
    }

    public function actuarial(LifeHealthReportService $reportService): View
    {
        $report = $reportService->generateLifeActuarialSummary();

        return view('insurance::life.actuarial', compact('report'));
    }

    public function show(int $id): View
    {
        $policy = LifePolicy::with('beneficiaries', 'unitHoldings.fund')->findOrFail($id);

        return view('insurance::life.show', compact('policy'));
    }

    public function store(Request $request, IssueLifePolicyAction $action): JsonResponse
    {
        $validated = $request->validate([
            'policy_number' => 'required|string|max:60|unique:ins_lh_life_policies,policy_number',
            'insured_party_id' => 'required|uuid',
            'plan_type' => 'required|string|in:TERM_LIFE,WHOLE_LIFE,ENDOWMENT_SAVING',
            'entry_age' => 'required|integer|min:18|max:75',
            'term_years' => 'required|integer|min:1|max:40',
            'sum_insured_idr' => 'required|integer|min:10000000',
            'beneficiaries' => 'required|array|min:1',
            'beneficiaries.*.beneficiary_party_id' => 'required|uuid',
            'beneficiaries.*.relationship' => 'required|string|max:40',
            'beneficiaries.*.share_percentage_bps' => 'required|integer|min:100|max:10000',
            'unit_link_allocations' => 'nullable|array',
        ]);

        $actorId = (string) ($request->user()?->id ?? 1);
        $idempotencyKey = $request->header('X-Idempotency-Key') ?? ('life:iss:'.$validated['policy_number']);

        $policy = $action->execute(
            policyNumber: $validated['policy_number'],
            insuredPartyId: $validated['insured_party_id'],
            planType: $validated['plan_type'],
            entryAge: (int) $validated['entry_age'],
            termYears: (int) $validated['term_years'],
            sumInsuredIdr: (int) $validated['sum_insured_idr'],
            beneficiaries: $validated['beneficiaries'],
            actorId: $actorId,
            idempotencyKey: $idempotencyKey,
            unitLinkAllocations: $validated['unit_link_allocations'] ?? [],
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Polis asuransi jiwa berhasil diterbitkan.',
            'policy' => $policy->load('beneficiaries'),
        ], 201);
    }

    public function claim(Request $request, ClaimLifeBenefitAction $action): JsonResponse
    {
        $validated = $request->validate([
            'policy_number' => 'required|string|exists:ins_lh_life_policies,policy_number',
            'death_certificate_ref' => 'required|string|max:80',
            'adjuster_id' => 'required|string',
        ]);

        $approverId = (string) ($request->user()?->id ?? 99);
        $idempotencyKey = $request->header('X-Idempotency-Key', 'life:clm:'.$validated['policy_number']);

        $claimResult = $action->execute(
            policyNumber: $validated['policy_number'],
            deathCertificateRef: $validated['death_certificate_ref'],
            adjusterId: $validated['adjuster_id'],
            approverId: $approverId,
            idempotencyKey: $idempotencyKey,
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Klaim santunan wafat berhasil diproses dengan four-eyes review.',
            'data' => $claimResult,
        ]);
    }

    public function underwrite(Request $request, int $id, UnderwriteMedicalApplicationAction $action): JsonResponse
    {
        $policy = LifePolicy::findOrFail($id);
        $validated = $request->validate([
            'height_cm' => 'required|integer|min:50|max:250',
            'weight_kg' => 'required|integer|min:20|max:300',
            'systolic_bp' => 'required|integer|min:70|max:240',
            'diastolic_bp' => 'required|integer|min:40|max:160',
            'is_smoker' => 'required|boolean',
            'has_diabetes' => 'required|boolean',
            'has_hypertension' => 'required|boolean',
            'has_heart_disease' => 'required|boolean',
            'family_cancer_history' => 'required|boolean',
        ]);

        $actorId = (string) ($request->user()?->id ?? 1);
        $idempotencyKey = $request->header('X-Idempotency-Key', 'uw:med:'.$policy->policy_number);

        $res = $action->execute(
            policyNumber: $policy->policy_number,
            medicalData: $validated,
            underwriterId: $actorId,
            idempotencyKey: $idempotencyKey,
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Underwriting medis berhasil dievaluasi.',
            'data' => $res,
        ]);
    }

    public function surrender(Request $request, int $id, SurrenderLifePolicyAction $action): JsonResponse
    {
        $policy = LifePolicy::findOrFail($id);
        $validated = $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        $actorId = (string) ($request->user()?->id ?? 1);
        $idempotencyKey = $request->header('X-Idempotency-Key', 'surr:'.$policy->policy_number);

        $res = $action->execute(
            policyNumber: $policy->policy_number,
            reason: $validated['reason'],
            actorId: $actorId,
            idempotencyKey: $idempotencyKey,
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Penebusan polis (surrender) berhasil diproses.',
            'data' => $res,
        ]);
    }
}
