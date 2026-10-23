<?php

declare(strict_types=1);

namespace Modules\Treasury\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Treasury\Application\Actions\ExecuteNotionalCashPoolSweepAction;
use Modules\Treasury\Application\Actions\RunMultiCurrencyFxRevaluationAction;
use Modules\Treasury\Application\Actions\SettleForwardFxHedgeContractAction;
use Modules\Treasury\Domain\Models\CashPool;
use Modules\Treasury\Domain\Models\ForwardContract;

class TreasuryOperationsController extends Controller
{
    public function revalueFx(
        Request $request,
        RunMultiCurrencyFxRevaluationAction $action
    ): JsonResponse {
        $data = $request->validate([
            'period' => 'required|string',
            'currency' => 'required|string|size:3',
            'foreign_balance_minor' => 'required|integer|min:0',
            'book_functional_idr' => 'required|integer|min:0',
            'spot_rate_scaled' => 'required|integer|min:1',
        ]);

        $res = $action->execute(
            $data['period'],
            $data['currency'],
            (int) $data['foreign_balance_minor'],
            (int) $data['book_functional_idr'],
            (int) $data['spot_rate_scaled']
        );

        return response()->json($res);
    }

    public function sweepCashPool(
        Request $request,
        CashPool $cashPool,
        ExecuteNotionalCashPoolSweepAction $action
    ): JsonResponse {
        $data = $request->validate([
            'current_sub_account_balance_idr' => 'required|integer',
        ]);

        $res = $action->execute(
            $cashPool,
            (int) $data['current_sub_account_balance_idr']
        );

        return response()->json($res);
    }

    public function settleHedge(
        Request $request,
        ForwardContract $contract,
        SettleForwardFxHedgeContractAction $action
    ): JsonResponse {
        $data = $request->validate([
            'spot_rate_at_maturity_scaled' => 'required|integer|min:1',
        ]);

        $res = $action->execute(
            $contract,
            (int) $data['spot_rate_at_maturity_scaled']
        );

        return response()->json($res);
    }
}
