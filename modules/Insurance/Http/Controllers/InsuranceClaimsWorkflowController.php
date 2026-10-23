<?php

declare(strict_types=1);

namespace Modules\Insurance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Insurance\Application\Actions\EvaluateActuarialClaimFraudScoreAction;
use Modules\Insurance\Application\Actions\IssueEmbeddedMicroInsurancePolicyAction;
use Modules\Insurance\Application\Actions\SettleApprovedInsuranceClaimLedgerAction;
use Modules\Insurance\Application\Actions\TriggerParametricOracleAutoClaimAction;
use Modules\Insurance\Domain\Models\InsuranceClaim;
use Modules\Insurance\Domain\Models\InsurancePolicy;
use Modules\Insurance\Domain\Models\InsuranceProduct;

class InsuranceClaimsWorkflowController extends Controller
{
    public function policies(Request $request): View
    {
        $policies = InsurancePolicy::with('product', 'user')->latest()->paginate(15);
        $products = InsuranceProduct::all();

        return view('insurance::insurance.policies', compact('policies', 'products'));
    }

    public function claims(Request $request): View
    {
        $claims = InsuranceClaim::with('policy.product')->latest()->paginate(15);

        return view('insurance::insurance.claims', compact('claims'));
    }

    public function fraudEvaluations(Request $request): View
    {
        $claims = InsuranceClaim::with('policy')->whereIn('status', ['pending_review', 'manual_investigation', 'siu_flagged'])->paginate(15);

        return view('insurance::insurance.fraud_evaluations', compact('claims'));
    }

    public function parametricTriggers(Request $request): View
    {
        $products = InsuranceProduct::where('is_embedded', true)->get();
        $activePolicies = InsurancePolicy::where('status', 'active')->get();

        return view('insurance::insurance.parametric_triggers', compact('products', 'activePolicies'));
    }

    public function issuePolicy(
        Request $request,
        IssueEmbeddedMicroInsurancePolicyAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'product_id' => 'required|integer',
            'subject_ref_type' => 'required|string|max:50',
            'subject_ref_id' => 'required|string|max:50',
            'duration_days' => 'nullable|integer|min:1',
        ]);

        $userId = auth()->id() ?? 1;
        $result = $action->handle(
            (int) $validated['product_id'],
            $userId,
            $validated['subject_ref_type'],
            $validated['subject_ref_id'],
            (int) ($validated['duration_days'] ?? 30)
        );

        return redirect()->route('insurance.workflow.policies')
            ->with('success', "Policy {$result['policy_number']} issued with coverage IDR ".number_format($result['coverage_limit_idr']));
    }

    public function triggerParametric(
        Request $request,
        TriggerParametricOracleAutoClaimAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'policy_id' => 'required|integer',
            'trigger_event_id' => 'required|string|max:100',
            'claim_amount_idr' => 'required|integer|min:1000',
        ]);

        $result = $action->handle(
            (int) $validated['policy_id'],
            $validated['trigger_event_id'],
            (int) $validated['claim_amount_idr']
        );

        return redirect()->route('insurance.workflow.claims')
            ->with('success', "Parametric claim {$result['claim_number']} filed for IDR ".number_format($result['payout_amount_idr']));
    }

    public function evaluateFraud(
        Request $request,
        EvaluateActuarialClaimFraudScoreAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'claim_id' => 'required|integer',
        ]);

        $result = $action->handle((int) $validated['claim_id']);

        return redirect()->route('insurance.workflow.fraud-evaluations')
            ->with('success', "Claim evaluated with score {$result['fraud_score']} ({$result['risk_decision']}).");
    }

    public function settleClaim(
        Request $request,
        SettleApprovedInsuranceClaimLedgerAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'claim_id' => 'required|integer',
        ]);

        $result = $action->handle((int) $validated['claim_id']);

        return redirect()->route('insurance.workflow.claims')
            ->with('success', "Claim {$result['claim']->claim_number} settled with payout IDR ".number_format($result['payout_amount_idr']));
    }
}
