<?php

declare(strict_types=1);

namespace Modules\Treasury\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Treasury\Domain\Models\PettyCashVoucher;
use Modules\Treasury\Http\Requests\ProcessPettyCashVoucherRequest;

final class PettyCashController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $actionType = $request->query('action_type');
        $currency = $request->query('currency', 'IDR');

        $query = PettyCashVoucher::query()->where('currency', strtoupper((string) $currency));
        if ($actionType) {
            $query->where('action_type', $actionType);
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
        $voucher = PettyCashVoucher::create([
            'voucher_number' => $request->getVoucherNumber(),
            'action_type' => $request->getActionType(),
            'amount' => $request->getAmount(),
            'currency' => $request->getCurrency(),
            'expense_category' => $request->getExpenseCategory(),
            'description' => $request->getDescription(),
            'receipt_reference' => $request->getReceiptReference(),
            'payee_name' => $request->getPayeeName(),
            'voucher_date' => now()->toDateString(),
            'status' => 'APPROVED',
            'actor_id' => auth()->id() ?? 1,
        ]);

        return response()->json([
            'status' => 'created',
            'voucher' => $voucher,
        ], 201);
    }

    public function show(PettyCashVoucher $voucher): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'voucher' => $voucher,
        ]);
    }
}
