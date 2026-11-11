<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\EnterpriseFinance\Application\Services\FinancialPeriodClosingService;
use Modules\EnterpriseFinance\Domain\Models\FinancialPeriodLock;

class FinancialPeriodCloseController extends Controller
{
    public function __construct(
        protected readonly FinancialPeriodClosingService $closingService
    ) {}

    public function index(Request $request): View
    {
        $locks = FinancialPeriodLock::query()
            ->orderBy('period', 'desc')
            ->paginate(15);

        return view('enterprise_finance::period_close', [
            'locks' => $locks,
        ]);
    }

    public function closePeriod(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'entity_code' => ['required', 'string', 'max:32'],
            'period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'revenue_minor' => ['required', 'integer', 'min:0'],
            'expense_minor' => ['required', 'integer', 'min:0'],
        ]);

        $this->closingService->closePeriod(
            entityCode: $validated['entity_code'],
            period: $validated['period'],
            revenueMinor: (int) $validated['revenue_minor'],
            expenseMinor: (int) $validated['expense_minor'],
            operatorId: (int) ($request->user()?->id ?? 1)
        );

        return redirect()->route('enterprise_finance.close')
            ->with('status', "Periode fiskal {$validated['period']} berhasil ditutup dan dikunci.");
    }
}

