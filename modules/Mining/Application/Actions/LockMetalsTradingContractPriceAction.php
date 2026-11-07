<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use InvalidArgumentException;
use Modules\Mining\Contracts\MetalsTradingLmeGateway;
use Modules\Mining\Domain\Enums\TradingContractStatus;
use Modules\Mining\Domain\Models\MiningMetalsTradingContract;
use Modules\Mining\Domain\StateMachines\MetalsTradingContractStateMachine;

final class LockMetalsTradingContractPriceAction
{
    public function __construct(
        private readonly MetalsTradingContractStateMachine $stateMachine,
        private readonly MetalsTradingLmeGateway $lmeGateway
    ) {}

    public function execute(
        MiningMetalsTradingContract $contract,
        ?int $overridePriceCentsPerTon = null
    ): MiningMetalsTradingContract {
        $priceCents = $overridePriceCentsPerTon;
        if (! $priceCents) {
            $quote = $this->lmeGateway->fetchCashSettlementPrice($contract->metal_symbol);
            $priceCents = $quote['cash_cents_ton'];
        }

        if ($priceCents <= 0) {
            throw new InvalidArgumentException('Harga patokan LME harus positif.');
        }

        $contract->fixed_price_usd_cents_per_ton = $priceCents;
        $this->stateMachine->transition($contract, TradingContractStatus::PRICE_LOCKED);

        return $contract;
    }
}
