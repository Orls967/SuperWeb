<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\EnterpriseFinance\Application\Services\CorporateFinancialReportGeneratorService;
use Modules\EnterpriseFinance\Domain\Models\FinancialPeriodLock;

class EnterpriseFinancialStatementController extends Controller
{
    public function __construct(
        protected readonly CorporateFinancialReportGeneratorService $reportGenerator
    ) {}

    public function index(Request $request): View
    {
        $periodLocks = FinancialPeriodLock::query()
            ->orderBy('period', 'desc')
            ->paginate(15);

        $entity = (string) ($request->query('entity') ?: 'HOLDING');
        $period = (string) ($request->query('period') ?: date('Y-m'));

        $balanceSheet = $this->reportGenerator->generateBalanceSheet($entity, date('Y-m-d'));
        $incomeStatement = $this->reportGenerator->generateIncomeStatement($entity, $period);

        return view('enterprise_finance::financial_statements', [
            'periodLocks' => $periodLocks,
            'balanceSheet' => $balanceSheet,
            'incomeStatement' => $incomeStatement,
        ]);
    }
}
