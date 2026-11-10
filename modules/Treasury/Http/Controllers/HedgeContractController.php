<?php

declare(strict_types=1);

namespace Modules\Treasury\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Treasury\Application\Actions\SettleForwardFxHedgeContractAction;
use Modules\Treasury\Domain\Enums\HedgeStatus;
use Modules\Treasury\Domain\Models\ForwardContract;
use Modules\Treasury\Domain\ValueObjects\RationalRate;
use Modules\Treasury\Http\Requests\StoreForwardContractRequest;
use Modules\Treasury\Http\Requests\TransitionForwardContractRequest;

final class HedgeContractController extends Controller
{
    public function __construct(
        protected readonly SettleForwardFxHedgeContractAction $settleAction
    ) {}

    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');
        $currency = $request->query('currency');

        $query = ForwardContract::query()->with('histories');
        if ($status) {
            $query->where('status', $status);
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
        $contract = ForwardContract::create([
            'contract_number' => $request->getContractNumber(),
            'counterparty_bank' => $request->getCounterpartyBank(),
            'currency' => $request->getCurrency(),
            'notional_amount' => $request->getNotionalAmount(),
            'contract_rate_numerator' => $request->getContractRateNumerator(),
            'contract_rate_denominator' => $request->getContractRateDenominator(),
            'spot_at_inception_numerator' => $request->getSpotAtInceptionNumerator(),
            'spot_at_inception_denominator' => $request->getSpotAtInceptionDenominator(),
            'start_date' => $request->getStartDate(),
            'maturity_date' => $request->getMaturityDate(),
            'hedge_type' => $request->getHedgeType(),
            'underlying_exposure' => $request->getUnderlyingExposure(),
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

        if ($action === 'settle') {
            $num = $request->getSettlementSpotNumerator();
            $den = $request->getSettlementSpotDenominator();
            $spotRate = ($num && $den) ? new RationalRate($num, $den) : null;

            $result = $this->settleAction->execute(
                contract: $contract,
                settlementSpotRate: $spotRate,
                actorId: auth()->id() ?? 1
            );

            return response()->json([
                'status' => 'settled',
                'contract' => $contract->fresh(),
                'result' => $result,
            ]);
        }

        $newStatus = match ($action) {
            'designate' => HedgeStatus::DESIGNATED,
            'activate' => HedgeStatus::ACTIVE,
            'mature' => HedgeStatus::MATURED,
            'terminate' => HedgeStatus::TERMINATED,
            'default' => HedgeStatus::DEFAULTED,
        };

        $contract->transitionTo($newStatus, auth()->id() ?? 1, $request->getReason());

        return response()->json([
            'status' => 'transitioned',
            'new_status' => $contract->status->value,
            'contract' => $contract->fresh(),
        ]);
    }
}
