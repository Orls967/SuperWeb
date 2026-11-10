<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\EnterpriseFinance\Domain\Models\ComplianceDeadline;
use Modules\EnterpriseFinance\Domain\Models\EfMaAcquisition;
use Modules\EnterpriseFinance\Domain\Models\EnterpriseBudget;
use Modules\EnterpriseFinance\Domain\Models\EnterpriseTaxSummary;
use Modules\EnterpriseFinance\Domain\Models\SodRule;
use Modules\EnterpriseFinance\Domain\Models\WorkingCapitalMetric;

class EnterpriseFinanceController extends Controller
{
    public function index(Request $request): View
    {
        $budgets = EnterpriseBudget::orderBy('cost_center_code')->limit(10)->get();
        $taxes = EnterpriseTaxSummary::orderByDesc('period')->limit(10)->get();
        $sodRules = SodRule::orderBy('risk_level')->limit(10)->get();
        $deadlines = ComplianceDeadline::orderBy('due_date')->limit(10)->get();

        return view('enterprise_finance::index', compact('budgets', 'taxes', 'sodRules', 'deadlines'));
    }

    public function budgetLifecycle(): View
    {
        $budgets = EnterpriseBudget::orderBy('budget_code')->limit(20)->get();

        return view('enterprise_finance::budget_cycle_lifecycle', compact('budgets'));
    }

    public function workingCapital(): View
    {
        $metrics = WorkingCapitalMetric::latest('id')->limit(20)->get();

        return view('enterprise_finance::working_capital_optimization', compact('metrics'));
    }

    public function sodMatrix(): View
    {
        $sodRules = SodRule::orderBy('risk_level')->get();

        return view('enterprise_finance::sod_matrix_governance', compact('sodRules'));
    }

    public function taxCompliance(): View
    {
        $taxes = EnterpriseTaxSummary::orderByDesc('period')->limit(20)->get();

        return view('enterprise_finance::tax_compliance_fiscal', compact('taxes'));
    }

    public function complianceCalendar(): View
    {
        $deadlines = ComplianceDeadline::orderBy('due_date')->limit(20)->get();

        return view('enterprise_finance::compliance_calendar', compact('deadlines'));
    }

    public function maGoodwill(): View
    {
        $acquisitions = EfMaAcquisition::latest('id')->limit(20)->get();

        return view('enterprise_finance::ma_goodwill_impairment', compact('acquisitions'));
    }

    public function reports(): View
    {
        return view('enterprise_finance::executive_reports');
    }
}
