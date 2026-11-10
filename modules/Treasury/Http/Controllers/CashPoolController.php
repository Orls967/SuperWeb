<?php

declare(strict_types=1);

namespace Modules\Treasury\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Treasury\Application\Actions\ExecuteNotionalCashPoolSweepAction;
use Modules\Treasury\Domain\Models\CashPool;
use Modules\Treasury\Http\Requests\ExecuteCashPoolSweepRequest;

final class CashPoolController extends Controller
{
    public function __construct(
        protected readonly ExecuteNotionalCashPoolSweepAction $sweepAction
    ) {}

    public function index(Request $request): JsonResponse
    {
        $pools = CashPool::query()
            ->with('accounts')
            ->where('is_active', true)
            ->get();

        return response()->json([
            'status' => 'success',
            'count' => $pools->count(),
            'pools' => $pools,
        ]);
    }

    public function show(CashPool $pool): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'pool' => $pool->load('accounts'),
        ]);
    }

    public function sweep(ExecuteCashPoolSweepRequest $request, CashPool $pool): JsonResponse
    {
        $result = $this->sweepAction->execute(
            pool: $pool,
            referenceDate: $request->getReferenceDate()
        );

        return response()->json([
            'status' => 'swept',
            'pool_code' => $pool->pool_code,
            'result' => $result,
        ]);
    }
}
