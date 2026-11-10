<?php

declare(strict_types=1);

namespace Modules\Treasury\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Treasury\Application\Actions\ReconcileBankStatementsAction;
use Modules\Treasury\Domain\Models\BankAccount;
use Modules\Treasury\Domain\Models\BankReconciliationMatch;
use Modules\Treasury\Domain\Models\BankReconciliationUnmatched;
use Modules\Treasury\Http\Requests\ReconcileBankStatementsRequest;

final class ReconciliationController extends Controller
{
    public function __construct(
        protected readonly ReconcileBankStatementsAction $reconcileAction
    ) {}

    public function index(Request $request): JsonResponse
    {
        $bankAccountId = $request->query('bank_account_id');
        $query = BankReconciliationMatch::query();

        if ($bankAccountId) {
            $query->where('bank_account_id', $bankAccountId);
        }

        $matches = $query->orderByDesc('matched_at')->limit(50)->get();

        return response()->json([
            'status' => 'success',
            'matches' => $matches,
        ]);
    }

    public function reconcile(ReconcileBankStatementsRequest $request): JsonResponse
    {
        $bankAccount = BankAccount::where('id', $request->getBankAccountId())
            ->orWhere('account_number', $request->getBankAccountId())
            ->firstOrFail();

        $result = $this->reconcileAction->execute(
            bankAccount: $bankAccount,
            toleranceDays: $request->getDateToleranceDays()
        );

        return response()->json([
            'status' => 'success',
            'bank_account' => $bankAccount->account_number,
            'result' => $result,
        ]);
    }

    public function unmatched(Request $request): JsonResponse
    {
        $bankAccountId = $request->query('bank_account_id');
        $query = BankReconciliationUnmatched::query();

        if ($bankAccountId) {
            $query->where('bank_account_id', $bankAccountId);
        }

        $unmatched = $query->orderByDesc('created_at')->limit(100)->get();

        return response()->json([
            'status' => 'success',
            'count' => $unmatched->count(),
            'unmatched' => $unmatched,
        ]);
    }
}
