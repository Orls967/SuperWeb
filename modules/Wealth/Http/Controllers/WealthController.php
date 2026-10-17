<?php

declare(strict_types=1);

namespace Modules\Wealth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Wealth\Application\Services\RoboAdvisorService;
use Modules\Wealth\Domain\Models\WmHolding;
use Modules\Wealth\Domain\Models\WmPlan;
use Modules\Wealth\Domain\Models\WmProfile;

class WealthController extends Controller
{
    public function index(Request $request): View
    {
        $profiles = WmProfile::with('plan')->latest()->take(10)->get();
        $plans = WmPlan::with('profile')->latest()->take(10)->get();
        $holdings = WmHolding::latest()->take(10)->get();

        return view('wealth::wealth.index', compact('profiles', 'plans', 'holdings'));
    }

    public function invest(
        Request $request,
        WmProfile $profile,
        RoboAdvisorService $service
    ): RedirectResponse {
        $validated = $request->validate([
            'wallet_balance_idr' => 'required|integer|min:0',
        ]);

        $invested = $service->executeSurplusAutoInvest(
            $profile,
            (int) $validated['wallet_balance_idr'],
            now()->toDateString()
        );

        $msg = ($invested > 0)
            ? 'Surplus Rp '.number_format($invested).' invested according to asset allocation plan.'
            : 'No surplus available after 2x emergency fund reservation.';

        return redirect()->route('wealth.index')->with('success', $msg);
    }
}
