<?php

declare(strict_types=1);

namespace Modules\Insurance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Insurance\Application\Actions\AdjudicateHealthClaimAction;
use Modules\Insurance\Application\Actions\AppealHealthClaimAction;
use Modules\Insurance\Application\Actions\AuthorizeHospitalCashlessAction;
use Modules\Insurance\Application\Services\LifeHealthReportService;
use Modules\Insurance\Domain\Models\GuaranteeLetter;
use Modules\Insurance\Domain\Models\HealthClaim;
use Modules\Insurance\Domain\Models\HealthPolicy;

final class HealthInsuranceController extends Controller
{
    public function index(): View
    {
        $policies = HealthPolicy::with('guaranteeLetters', 'claims')->latest()->paginate(20);

        return view('insurance::health.index', compact('policies'));
    }

    public function analytics(LifeHealthReportService $reportService): View
    {
        $report = $reportService->generateHealthLossRatioReport();

        return view('insurance::health.analytics', compact('report'));
    }

    public function guaranteeLetters(): View
    {
        $letters = GuaranteeLetter::with('healthPolicy')->latest()->paginate(20);

        return view('insurance::health.guarantee_letters', compact('letters'));
    }

    public function requestGl(Request $request, AuthorizeHospitalCashlessAction $action): JsonResponse
    {
        $validated = $request->validate([
            'policy_number' => 'required|string|exists:ins_lh_health_policies,policy_number',
            'hospital_encounter_ref' => 'required|string|max:80',
        ]);

        $actorId = (string) ($request->user()?->id ?? 1);
        $idempotencyKey = $request->header('X-Idempotency-Key', 'gl:req:'.$validated['hospital_encounter_ref']);

        $gl = $action->execute(
            policyNumber: $validated['policy_number'],
            hospitalEncounterRef: $validated['hospital_encounter_ref'],
            actorId: $actorId,
            idempotencyKey: $idempotencyKey,
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Surat Jaminan (GL) rawat inap cashless berhasil diterbitkan.',
            'guarantee_letter' => $gl,
        ], 201);
    }

    public function claims(): View
    {
        $claims = HealthClaim::with('healthPolicy', 'guaranteeLetter')->latest()->paginate(20);

        return view('insurance::health.claims', compact('claims'));
    }

    public function adjudicate(Request $request, AdjudicateHealthClaimAction $action): JsonResponse
    {
        $validated = $request->validate([
            'claim_number' => 'required|string|max:60|unique:ins_lh_health_claims,claim_number',
            'policy_number' => 'required|string|exists:ins_lh_health_policies,policy_number',
            'claim_type' => 'required|string|in:CASHLESS,REIMBURSEMENT',
            'incurred_amount_idr' => 'required|integer|min:10000',
            'gl_number' => 'nullable|string|exists:ins_lh_guarantee_letters,gl_number',
        ]);

        $actorId = (string) ($request->user()?->id ?? 1);
        $idempotencyKey = $request->header('X-Idempotency-Key', 'claim:adj:'.$validated['claim_number']);

        $claim = $action->execute(
            claimNumber: $validated['claim_number'],
            policyNumber: $validated['policy_number'],
            claimType: $validated['claim_type'],
            incurredAmountIdr: (int) $validated['incurred_amount_idr'],
            glNumber: $validated['gl_number'] ?? null,
            actorId: $actorId,
            idempotencyKey: $idempotencyKey,
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Klaim kesehatan berhasil diadjudikasi dan diposting ke ledger.',
            'claim' => $claim,
        ], 201);
    }

    public function appeal(Request $request, int $id, AppealHealthClaimAction $action): JsonResponse
    {
        $claim = HealthClaim::findOrFail($id);
        $validated = $request->validate([
            'appeal_reason' => 'required|string|max:255',
            'medical_director_review' => 'required|string|max:255',
            'decision' => 'required|string|in:APPROVE,REJECT',
            'adjusted_approved_amount_idr' => 'required|integer|min:0',
        ]);

        $actorId = (string) ($request->user()?->id ?? 1);
        $idempotencyKey = $request->header('X-Idempotency-Key') ?? ('appeal:'.$claim->claim_number);

        $res = $action->execute(
            claimNumber: $claim->claim_number,
            appealReason: $validated['appeal_reason'],
            medicalDirectorReview: $validated['medical_director_review'],
            decision: (string) $validated['decision'],
            adjustedApprovedAmountIdr: (int) $validated['adjusted_approved_amount_idr'],
            actorId: $actorId,
            idempotencyKey: $idempotencyKey,
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Proses banding klaim kesehatan selesai.',
            'claim' => $res,
        ]);
    }
}
