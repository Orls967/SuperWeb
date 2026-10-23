<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\EnterpriseFinance\Application\Actions\ConsolidateGroupMultiEntityTrialBalanceAction;
use Modules\EnterpriseFinance\Application\Actions\EnforceDepartmentBudgetGuardrailAction;
use Modules\EnterpriseFinance\Application\Actions\MonitorDebtCovenantRatiosAction;
use Modules\EnterpriseFinance\Domain\Models\EfSegmentFinancialReport;
use Modules\EnterpriseFinance\Domain\Models\EnterpriseBudget;

class EnterpriseFinanceGovernanceController extends Controller
{
    public function index(): mixed
    {
        $budgets = EnterpriseBudget::latest()->take(10)->get();
        $reports = EfSegmentFinancialReport::latest()->take(5)->get();

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'budgets' => $budgets,
                'consolidated_reports' => $reports,
            ]);
        }

        return view('enterprise_finance::enterprise_finance_dashboard', compact('budgets', 'reports'));
    }

    public function consolidateTrialBalance(
        Request $request,
        ConsolidateGroupMultiEntityTrialBalanceAction $action
    ): JsonResponse {
        $data = $request->validate([
            'period_quarter' => 'required|string|size:7',
            'entity_balances' => 'required|array|min:1',
            'entity_balances.*.revenue_minor' => 'required|integer',
            'entity_balances.*.ebitda_minor' => 'required|integer',
        ]);

        $result = $action->execute($data['period_quarter'], $data['entity_balances']);

        return response()->json([
            'success' => true,
            'message' => 'Group multi-entity trial balance consolidated.',
            'data' => $result,
        ], 201);
    }

    public function enforceBudget(
        Request $request,
        EnterpriseBudget $budget,
        EnforceDepartmentBudgetGuardrailAction $action
    ): JsonResponse {
        $data = $request->validate([
            'requested_amount_idr' => 'required|integer|min:1',
            'mode' => 'nullable|string|in:spend,encumber',
        ]);

        $result = $action->execute(
            $budget,
            (int) $data['requested_amount_idr'],
            $data['mode'] ?? 'spend'
        );

        return response()->json([
            'success' => true,
            'message' => 'Budget guardrail evaluated successfully.',
            'data' => $result,
        ]);
    }

    public function monitorCovenants(
        Request $request,
        MonitorDebtCovenantRatiosAction $action
    ): JsonResponse {
        $data = $request->validate([
            'total_debt_idr' => 'required|integer|min:0',
            'total_equity_idr' => 'required|integer|min:0',
            'ebitda_idr' => 'required|integer|min:0',
            'annual_interest_expense_idr' => 'required|integer|min:0',
            'max_de_ratio' => 'nullable|numeric|min:0.1',
            'min_icr' => 'nullable|numeric|min:0.1',
        ]);

        $result = $action->execute(
            (int) $data['total_debt_idr'],
            (int) $data['total_equity_idr'],
            (int) $data['ebitda_idr'],
            (int) $data['annual_interest_expense_idr'],
            (float) ($data['max_de_ratio'] ?? 2.50),
            (float) ($data['min_icr'] ?? 3.00)
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}
