<?php

declare(strict_types=1);

namespace Modules\Treasury\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Treasury\Application\Actions\SettleForwardContractAction;
use Modules\Treasury\Domain\Enums\HedgeStatus;
use Modules\Treasury\Domain\Models\Currency;
use Modules\Treasury\Domain\Models\ForwardContract;
use Modules\Treasury\Domain\StateMachines\HedgeContractStateMachine;
use Modules\Treasury\Http\Requests\StoreForwardContractRequest;
use Modules\Treasury\Http\Requests\TransitionForwardContractRequest;

final class HedgeContractController extends Controller
{
    public function __construct(
        protected readonly SettleForwardContractAction $settleAction,
        protected readonly HedgeContractStateMachine $stateMachine
    ) {}

    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');
        $currency = $request->query('currency');

        $query = ForwardContract::query()->with('histories');
        if ($status) {
            $query->where('status', strtolower((string) $status));
        }
        if ($currency) {
            $query->where('currency', strtoupper((string) $currency));
        }

        $contracts = $query->orderByDesc('created_at')->limit(50)->get();

        return response()->json([
            'status' => 'success',
            'count' => $contracts->count(),
            'contracts' => $contracts,
        ]);
    }

    public function store(StoreForwardContractRequest $request): JsonResponse
    {
        $ccy = $request->getCurrency();
        Currency::firstOrCreate(
            ['code' => $ccy],
            ['name' => $ccy, 'symbol' => $ccy, 'minor_units' => 2, 'is_active' => true]
        );

        $forwardRateScaled = (int) intdiv(
            $request->getContractRateNumerator() * 10000,
            max(1, $request->getContractRateDenominator())
        );

        $contract = ForwardContract::create([
            'contract_number' => $request->getContractNumber(),
            'currency' => $ccy,
            'creator_id' => $request->user()?->id,
            'notional_foreign_amount' => $request->getNotionalAmount(),
            'forward_rate_scaled' => $forwardRateScaled,
            'contract_rate_numerator' => $request->getContractRateNumerator(),
            'contract_rate_denominator' => $request->getContractRateDenominator(),
            'spot_rate_numerator' => $request->getSpotAtInceptionNumerator(),
            'spot_rate_denominator' => $request->getSpotAtInceptionDenominator(),
            'maturity_date' => $request->getMaturityDate(),
            'mtm_value_idr' => 0,
            'status' => HedgeStatus::DRAFT->value,
        ]);

        return response()->json([
            'status' => 'created',
            'contract' => $contract,
        ], 201);
    }

    public function show(ForwardContract $contract): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'contract' => $contract->load('histories'),
        ]);
    }

    public function transition(TransitionForwardContractRequest $request, ForwardContract $contract): JsonResponse
    {
        $action = $request->getAction();
        $actorId = $request->user()?->id ?? 1;

        if ($action === 'settle') {
            $num = $request->getSettlementSpotNumerator() ?? 15700;
            $den = max(1, $request->getSettlementSpotDenominator() ?? 1);
            $spotRateScaled = (int) intdiv($num * 10000, $den);

            $result = $this->settleAction->execute(
                contract: $contract,
                actualSpotRateScaled: $spotRateScaled,
                userId: $actorId
            );

            return response()->json([
                'status' => 'settled',
                'contract' => $contract->fresh(),
                'result' => $result,
            ]);
        }

        $newStatus = match ($action) {
            'designate', 'approve' => HedgeStatus::APPROVED,
            'book' => HedgeStatus::BOOKED,
            'activate' => HedgeStatus::ACTIVE,
            'mature' => HedgeStatus::MATURED,
            'roll' => HedgeStatus::ROLLED,
            'terminate', 'default', 'cancel' => HedgeStatus::CANCELLED,
            default => HedgeStatus::APPROVED,
        };

        $updated = $this->stateMachine->transition(
            contract: $contract,
            targetStatus: $newStatus,
            actorId: $actorId,
            reason: $request->getReason()
        );

        return response()->json([
            'status' => 'transitioned',
            'new_status' => $updated->status instanceof HedgeStatus ? $updated->status->value : (string) $updated->status,
            'contract' => $updated,
        ]);
    }
}
