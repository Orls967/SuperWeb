<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\StateMachines;

use Modules\Mining\Domain\Enums\TradingContractStatus;
use Modules\Mining\Domain\Exceptions\InvalidMiningStateTransitionException;
use Modules\Mining\Domain\Models\MiningMetalsTradingContract;

/**
 * State machine untuk siklus kontrak perdagangan & lindung nilai logam LME (121.3).
 * Mengawal 8 status: DRAFT -> PRICE_LOCKED -> ACTIVE_HEDGED -> MARGIN_CALLED -> SETTLEMENT_PENDING -> PHYSICAL_DELIVERED -> FINANCIALLY_SETTLED / TERMINATED.
 */
class MetalsTradingContractStateMachine
{
    protected ?MiningMetalsTradingContract $contract = null;

    public function __construct(?MiningMetalsTradingContract $contract = null)
    {
        $this->contract = $contract;
    }

    public function canTransition(TradingContractStatus $current, TradingContractStatus $target): bool
    {
        return $current->canTransitionTo($target);
    }

    public function transitionTo(TradingContractStatus $targetStatus): void
    {
        if (! $this->contract) {
            throw new InvalidMiningStateTransitionException('Instance MiningMetalsTradingContract belum dikonfigurasi dalam state machine.');
        }

        $this->transition($this->contract, $targetStatus);
    }

    public function transition(MiningMetalsTradingContract $contract, TradingContractStatus $targetStatus): void
    {
        $currentVal = strtoupper((string) ($contract->status ?? 'DRAFT'));
        $currentStatus = TradingContractStatus::tryFrom($currentVal) ?? TradingContractStatus::DRAFT;

        if ($currentStatus === $targetStatus) {
            return;
        }

        if (! $currentStatus->canTransitionTo($targetStatus)) {
            throw InvalidMiningStateTransitionException::forEntity(
                'MiningMetalsTradingContract',
                $currentStatus->value,
                $targetStatus->value,
                "Kontrak perdagangan logam {$contract->contract_number} ({$currentStatus->value}) tidak dapat dialihkan ke {$targetStatus->value}."
            );
        }

        $this->assertTransitionGuard($contract, $currentStatus, $targetStatus);

        $contract->status = $targetStatus->value;
        $contract->save();
    }

    protected function assertTransitionGuard(
        MiningMetalsTradingContract $contract,
        TradingContractStatus $current,
        TradingContractStatus $target
    ): void {
        if ($target === TradingContractStatus::PRICE_LOCKED && $contract->fixed_price_usd_cents_per_ton <= 0) {
            throw InvalidMiningStateTransitionException::forEntity(
                'MiningMetalsTradingContract',
                $current->value,
                $target->value,
                'Penguncian harga LME memerlukan penetapan fixed_price_usd_cents_per_ton > 0.'
            );
        }

        if ($target === TradingContractStatus::ACTIVE_HEDGED && $contract->initial_margin_idr <= 0) {
            throw InvalidMiningStateTransitionException::forEntity(
                'MiningMetalsTradingContract',
                $current->value,
                $target->value,
                'Aktivasi posisi lindung nilai memerlukan setoran initial_margin_idr > 0.'
            );
        }

        if ($target === TradingContractStatus::FINANCIALLY_SETTLED && $contract->contract_quantity_metric_tons <= 0.0) {
            throw InvalidMiningStateTransitionException::forEntity(
                'MiningMetalsTradingContract',
                $current->value,
                $target->value,
                'Penyelesaian finansial memerlukan kuantitas kontrak fisik/derivatif valid.'
            );
        }
    }
}
