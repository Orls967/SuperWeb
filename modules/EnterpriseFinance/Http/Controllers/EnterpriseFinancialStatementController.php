<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\EnterpriseFinance\Domain\Models\FinancialPeriodLock;

class EnterpriseFinancialStatementController extends Controller
{
    public function index(Request $request): View
    {
        $periodLocks = FinancialPeriodLock::query()
            ->orderBy('period', 'desc')
            ->paginate(15);

        return view('enterprise_finance::financial_statements', [
            'periodLocks' => $periodLocks,
        ]);
    }
}

