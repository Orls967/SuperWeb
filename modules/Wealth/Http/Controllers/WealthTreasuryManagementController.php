<?php

declare(strict_types=1);

namespace Modules\Wealth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Wealth\Application\Actions\AllocateTargetMptPortfolioWeightsAction;
use Modules\Wealth\Application\Actions\ComputeInvestorRiskToleranceProfileAction;
use Modules\Wealth\Application\Actions\RebalancePortfolioDriftIdempotentAction;
use Modules\Wealth\Application\Actions\SweepCorporateIdleCashToOvernightYieldAction;
use Modules\Wealth\Domain\Models\WmOrder;
use Modules\Wealth\Domain\Models\WmPlan;
use Modules\Wealth\Domain\Models\WmProfile;

class WealthTreasuryManagementController extends Controller
{
    public function riskProfiles(Request $request): View
    {
        $profiles = WmProfile::with('user', 'plan')->latest()->paginate(15);

        return view('wealth::wealth.risk_profiles', compact('profiles'));
    }

    public function mptAllocation(Request $request): View
    {
        $plans = WmPlan::with('profile.user')->latest()->paginate(15);

        return view('wealth::wealth.mpt_allocation', compact('plans'));
    }

    public function portfolioRebalancing(Request $request): View
    {
        $profiles = WmProfile::with('holdings', 'plan')->get();
        $recentOrders = WmOrder::with('profile.user')->latest()->take(15)->get();

        return view('wealth::wealth.portfolio_rebalancing', compact('profiles', 'recentOrders'));
    }

    public function cashSweep(Request $request): View
    {
        $profiles = WmProfile::with('holdings')->where('auto_invest_enabled', true)->get();

        return view('wealth::wealth.cash_sweep', compact('profiles'));
    }

    public function computeProfile(
        Request $request,
        ComputeInvestorRiskToleranceProfileAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'monthly_spend_baseline_idr' => 'required|integer|min:100000',
            'risk_survey_score' => 'required|integer|min:0|max:100',
        ]);

        $userId = auth()->id() ?? 1;
        $result = $action->handle(
            $userId,
            (int) $validated['monthly_spend_baseline_idr'],
            (int) $validated['risk_survey_score']
        );

        return redirect()->route('wealth.treasury.risk-profiles')
            ->with('success', "Risk profile computed: {$result['risk_tier']} (Emergency fund target: IDR ".number_format($result['emergency_fund_target_idr']).')');
    }

    public function allocateMpt(
        Request $request,
        AllocateTargetMptPortfolioWeightsAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'profile_id' => 'required|integer',
        ]);

        $result = $action->handle((int) $validated['profile_id']);

        return redirect()->route('wealth.treasury.mpt-allocation')
            ->with('success', "MPT weights allocated: MF {$result['mutual_funds_pct']}%, Gold {$result['gold_pct']}%, Crypto {$result['crypto_pct']}%");
    }

    public function rebalance(
        Request $request,
        RebalancePortfolioDriftIdempotentAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'profile_id' => 'required|integer',
        ]);

        $result = $action->handle((int) $validated['profile_id']);

        $msg = $result['rebalanced']
            ? "Portfolio rebalanced successfully: {$result['orders_count']} orders executed."
            : 'Portfolio is within drift tolerance; no rebalancing needed.';

        return redirect()->route('wealth.treasury.portfolio-rebalancing')->with('success', $msg);
    }

    public function sweepCash(
        Request $request,
        SweepCorporateIdleCashToOvernightYieldAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'profile_id' => 'required|integer',
            'available_cash_idr' => 'required|integer|min:1000',
            'buffer_idr' => 'nullable|integer|min:0',
        ]);

        $result = $action->handle(
            (int) $validated['profile_id'],
            (int) $validated['available_cash_idr'],
            (int) ($validated['buffer_idr'] ?? 20000000)
        );

        return redirect()->route('wealth.treasury.cash-sweep')
            ->with('success', 'Swept IDR '.number_format($result['swept_amount_idr']).' into overnight yield fund.');
    }
}
