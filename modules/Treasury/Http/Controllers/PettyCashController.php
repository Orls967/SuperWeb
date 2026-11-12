<?php

declare(strict_types=1);

namespace Modules\Treasury\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Treasury\Application\Actions\ProcessPettyCashVoucherAction;
use Modules\Treasury\Domain\Models\BankAccount;
use Modules\Treasury\Domain\Models\PettyCashVoucher;
use Modules\Treasury\Http\Requests\ProcessPettyCashVoucherRequest;

final class PettyCashController extends Controller
{
    public function __construct(
        protected readonly ProcessPettyCashVoucherAction $voucherAction
    ) {}

    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');

        $query = PettyCashVoucher::query()->with('bankAccount');
        if ($status) {
            $query->where('status', strtolower((string) $status));
        }

        $vouchers = $query->orderByDesc('voucher_date')->limit(50)->get();

        return response()->json([
            'status' => 'success',
            'count' => $vouchers->count(),
            'vouchers' => $vouchers,
        ]);
    }

    public function store(ProcessPettyCashVoucherRequest $request): JsonResponse
    {
        $pettyAccount = BankAccount::where('is_petty_cash', true)->firstOrFail();

        $voucher = $this->voucherAction->submitVoucher(
            bankAccount: $pettyAccount,
            amount: $request->getAmount(),
            payee: $request->getPayeeName() ?? 'Operational Payee',
            expenseAccountCode: $request->getExpenseCategory() ?? 'trs:petty_cash_expense:IDR',
            description: $request->getDescription(),
            creatorId: $request->user()?->id ?? 1,
            customVoucherNumber: $request->getVoucherNumber()
        );

        return response()->json([
            'status' => 'created',
            'voucher' => $voucher,
        ], 201);
    }

    public function show(PettyCashVoucher $voucher): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'voucher' => $voucher->load('bankAccount'),
        ]);
    }
}
