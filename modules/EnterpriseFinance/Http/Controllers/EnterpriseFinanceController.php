<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\EnterpriseFinance\Domain\Models\ComplianceDeadline;
use Modules\EnterpriseFinance\Domain\Models\EnterpriseBudget;
use Modules\EnterpriseFinance\Domain\Models\EnterpriseTaxSummary;
use Modules\EnterpriseFinance\Domain\Models\SodRule;

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
}
