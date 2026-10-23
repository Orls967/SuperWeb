<?php

declare(strict_types=1);

namespace Modules\Insurance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Insurance\Application\Services\InsurTechClaimsService;
use Modules\Insurance\Domain\Models\InsuranceClaim;
use Modules\Insurance\Domain\Models\InsurancePolicy;
use Modules\Insurance\Domain\Models\InsuranceProduct;

class InsuranceController extends Controller
{
    public function index(Request $request): View
    {
        $products = InsuranceProduct::latest()->take(10)->get();
        $policies = InsurancePolicy::with('product')->latest()->take(10)->get();
        $claims = InsuranceClaim::with('policy')->latest()->take(10)->get();

        return view('insurance::insurance.index', compact('products', 'policies', 'claims'));
    }

    public function issue(
        Request $request,
        InsuranceProduct $product,
        InsurTechClaimsService $service
    ): RedirectResponse {
        $user = $request->user();

        $policy = $service->issueEmbeddedPolicy(
            product: $product,
            userId: $user->id,
            refType: 'TRIP',
            refId: 'TRIP-'.rand(1000, 9999),
            durationDays: 7
        );

        return redirect()->route('insurance.index')->with('success', "Insurance policy {$policy->policy_number} issued successfully.");
    }
}
