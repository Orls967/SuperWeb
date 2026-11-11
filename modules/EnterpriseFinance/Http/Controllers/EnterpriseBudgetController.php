<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\EnterpriseFinance\Domain\Models\BudgetEncumbrance;
use Modules\EnterpriseFinance\Domain\Models\EnterpriseBudget;

class EnterpriseBudgetController extends Controller
{
    public function index(Request $request): View
    {
        $budgets = EnterpriseBudget::query()
            ->with('encumbrances')
            ->orderBy('id', 'desc')
            ->paginate(15);

        return view('enterprise_finance::budgets_hierarchy', [
            'budgets' => $budgets,
        ]);
    }

    public function commitments(int $id): View
    {
        $budget = EnterpriseBudget::query()->findOrFail($id);
        $commitments = BudgetEncumbrance::query()
            ->where('budget_id', $id)
            ->orderBy('reserved_at', 'desc')
            ->paginate(15);

        return view('enterprise_finance::budgets_hierarchy', [
            'selectedBudget' => $budget,
            'commitments' => $commitments,
            'budgets' => EnterpriseBudget::query()->paginate(15),
        ]);
    }
}

