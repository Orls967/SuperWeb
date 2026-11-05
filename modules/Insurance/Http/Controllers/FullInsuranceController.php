<?php

declare(strict_types=1);

namespace Modules\Insurance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Insurance\Application\Services\FullInsuranceService;
use Modules\Insurance\Domain\Models\InsuranceClaimPenuh;
use Modules\Insurance\Domain\Models\InsurancePolicyPenuh;
use Modules\Insurance\Domain\Models\InsuranceProductPenuh;

final class FullInsuranceController extends Controller
{
    public function __construct(
        private readonly FullInsuranceService $insuranceService
    ) {}

    public function products()
    {
        $products = InsuranceProductPenuh::where('is_active', true)->paginate(15);

        return view('insurance::products', compact('products'));
    }

    public function policies(Request $request)
    {
        $query = InsurancePolicyPenuh::with('product');

        // IDOR protection: non-admin & non-underwriter only see their own policies
        $user = $request->user();
        if ($user && ! in_array($user->role, ['admin', 'superadmin', 'underwriter', 'actuary', 'claims_adjuster', 'reinsurance_mgr'], true)) {
            $query->where('customer_party_ref', $user->id);
        }

        $policies = $query->paginate(15);

        return view('insurance::policies', compact('policies'));
    }

    public function policyDetail(Request $request, int $id)
    {
        $policy = InsurancePolicyPenuh::with(['product', 'schedules', 'claims'])->findOrFail($id);

        $user = $request->user();
        if ($user && ! in_array($user->role, ['admin', 'superadmin', 'underwriter', 'actuary', 'claims_adjuster', 'reinsurance_mgr'], true)) {
            if ($policy->customer_party_ref !== $user->id) {
                abort(403, 'Akses ditolak: bukan pemilik polis yang sah.');
            }
        }

        $uprAmount = $this->insuranceService->calculateUnearnedPremium($policy);

        return view('insurance::policy_detail', compact('policy', 'uprAmount'));
    }

    public function claims(Request $request)
    {
        $query = InsuranceClaimPenuh::with('policy.product');

        $user = $request->user();
        if ($user && ! in_array($user->role, ['admin', 'superadmin', 'underwriter', 'actuary', 'claims_adjuster', 'reinsurance_mgr'], true)) {
            $query->whereHas('policy', function ($q) use ($user) {
                $q->where('customer_party_ref', $user->id);
            });
        }

        $claims = $query->paginate(15);

        return view('insurance::claims', compact('claims'));
    }

    public function claimDetail(Request $request, int $id)
    {
        $claim = InsuranceClaimPenuh::with('policy.product')->findOrFail($id);

        $user = $request->user();
        if ($user && ! in_array($user->role, ['admin', 'superadmin', 'underwriter', 'actuary', 'claims_adjuster', 'reinsurance_mgr'], true)) {
            if ($claim->policy->customer_party_ref !== $user->id) {
                abort(403, 'Akses ditolak: bukan pemilik klaim yang sah.');
            }
        }

        return view('insurance::claim_detail', compact('claim'));
    }

    public function actuarial(Request $request): JsonResponse
    {
        $category = (string) $request->query('category', 'VEHICLE');
        $reserves = $this->insuranceService->estimateCategoryIbnr($category);

        return response()->json([
            'category' => strtoupper($category),
            'ibnr_estimates' => $reserves,
        ]);
    }
}
