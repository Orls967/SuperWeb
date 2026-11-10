<?php

declare(strict_types=1);

namespace Modules\Treasury\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Treasury\Application\Actions\DrawFacilityAction;
use Modules\Treasury\Application\Services\TreasuryService;
use Modules\Treasury\Domain\Models\CreditFacility;
use Modules\Treasury\Domain\Models\ForwardContract;

class TreasuryApiController extends Controller
{
    public function __construct(
        protected TreasuryService $treasuryService
    ) {}

    public function postFxTransfer(Request $request): JsonResponse
    {
        $headerKey = $request->header('Idempotency-Key');
        if (! $headerKey) {
            return response()->json([
                'message' => 'Header Idempotency-Key wajib disertakan untuk menjamin konsistensi transfer valas.',
            ], 422);
        }

        $data = $request->validate([
            'from_account' => 'required|string',
            'to_account' => 'required|string',
            'foreign_amount' => 'required|integer|min:1',
            'foreign_currency' => 'required|string|size:3',
            'description' => 'required|string|max:255',
            'idempotency_key' => 'nullable|string',
        ]);

        $res = $this->treasuryService->postMultiCurrency(
            fromAccountCode: $data['from_account'],
            toAccountCode: $data['to_account'],
            foreignAmount: (int) $data['foreign_amount'],
            foreignCurrency: $data['foreign_currency'],
            idempotencyKey: $headerKey,
            description: $data['description']
        );

        return response()->json($res, 201);
    }

    public function getHedge(Request $request, ForwardContract $contract): JsonResponse
    {
        return response()->json([
            'contract' => $contract->load(['histories', 'currencyRef']),
        ]);
    }

    public function drawFacility(
        Request $request,
        CreditFacility $facility,
        DrawFacilityAction $action
    ): JsonResponse {
        $data = $request->validate([
            'amount_idr' => 'required|integer|min:1',
            'der_permille' => 'nullable|integer',
        ]);

        $res = $action->execute(
            facility: $facility,
            amountIdr: (int) $data['amount_idr'],
            currentDerPermille: (int) ($data['der_permille'] ?? 1500),
            idempotencyKey: $request->header('Idempotency-Key'),
            userId: $request->user()?->id
        );

        return response()->json($res);
    }
}
