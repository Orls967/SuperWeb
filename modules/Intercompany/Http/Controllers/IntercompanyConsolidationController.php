<?php

declare(strict_types=1);

namespace Modules\Intercompany\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Intercompany\Application\Actions\ApplyArmsLengthTransferPricingMarkupAction;
use Modules\Intercompany\Application\Actions\CreateMirroredIntercompanyTransactionAction;
use Modules\Intercompany\Application\Actions\GenerateIntercompanyEliminationJournalAction;
use Modules\Intercompany\Domain\Models\EliminationEntry;
use Modules\Intercompany\Domain\Models\IntercompanyTransaction;
use Modules\Intercompany\Domain\Models\TransferPricingRule;

class IntercompanyConsolidationController extends Controller
{
    public function index(): mixed
    {
        $transactions = IntercompanyTransaction::latest()->take(10)->get();
        $rules = TransferPricingRule::where('is_active', true)->get();
        $eliminations = EliminationEntry::latest()->take(10)->get();

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'transactions' => $transactions,
                'transfer_pricing_rules' => $rules,
                'elimination_entries' => $eliminations,
            ]);
        }

        return view('intercompany::intercompany_matrix', compact('transactions', 'rules', 'eliminations'));
    }

    public function createMirrorTransaction(
        Request $request,
        CreateMirroredIntercompanyTransactionAction $action
    ): JsonResponse {
        $data = $request->validate([
            'selling_entity' => 'required|string|max:64',
            'buying_entity' => 'required|string|max:64|different:selling_entity',
            'description' => 'required|string|max:255',
            'amount_idr' => 'required|integer|min:1',
            'currency' => 'nullable|string|size:3',
            'sales_invoice_ref' => 'nullable|string|max:32',
            'purchase_bill_ref' => 'nullable|string|max:32',
        ]);

        $tx = $action->execute($data);

        return response()->json([
            'success' => true,
            'message' => 'Mirrored intercompany transaction recorded.',
            'transaction' => $tx,
        ], 201);
    }

    public function applyMarkup(
        Request $request,
        TransferPricingRule $rule,
        ApplyArmsLengthTransferPricingMarkupAction $action
    ): JsonResponse {
        $data = $request->validate([
            'base_cost_idr' => 'required|integer|min:1',
            'requested_margin_percent' => 'required|numeric',
        ]);

        $result = $action->execute(
            $rule,
            (int) $data['base_cost_idr'],
            (float) $data['requested_margin_percent']
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    public function postElimination(
        Request $request,
        GenerateIntercompanyEliminationJournalAction $action
    ): JsonResponse {
        $data = $request->validate([
            'period' => 'required|string|size:7',
            'elimination_type' => 'required|string|in:RECIPROCAL_AR_AP,REVENUE_EXPENSE,UNREALIZED_INVENTORY_PROFIT',
            'debit_account' => 'required|string|max:64',
            'credit_account' => 'required|string|max:64',
            'amount_idr' => 'required|integer|min:1',
        ]);

        $entry = $action->execute($data);

        return response()->json([
            'success' => true,
            'message' => 'Consolidation elimination journal generated.',
            'elimination_entry' => $entry,
        ], 201);
    }
}
