<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Modules\Mining\Domain\Enums\TradingContractStatus;
use Modules\Mining\Domain\Models\MiningMetalsTradingContract;
use Modules\Mining\Domain\StateMachines\MetalsTradingContractStateMachine;

final class SettleMetalsTradingContractAction
{
    public function __construct(
        private readonly MetalsTradingContractStateMachine $stateMachine
    ) {}

    public function execute(
        MiningMetalsTradingContract $contract,
        bool $isPhysicalDelivery = true
    ): MiningMetalsTradingContract {
        if ($isPhysicalDelivery) {
            $this->stateMachine->transition($contract, TradingContractStatus::PHYSICAL_DELIVERED);
        }

        $this->stateMachine->transition($contract, TradingContractStatus::FINANCIALLY_SETTLED);

        return $contract;
    }
}
