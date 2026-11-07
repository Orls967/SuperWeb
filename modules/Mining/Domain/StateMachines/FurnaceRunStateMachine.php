<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\StateMachines;

use Modules\Mining\Domain\Enums\FurnaceRunStatus;
use Modules\Mining\Domain\Exceptions\InvalidMiningStateTransitionException;
use Modules\Mining\Domain\Models\MiningSmelterFurnaceRun;

/**
 * State machine untuk siklus batch peleburan tanur smelter (121.1).
 * Mengawal 8 status: DRAFT -> CHARGING -> PREHEATING -> SMELTING -> TAPPING -> COMPLETED/ABORTED -> COOLING.
 */
class FurnaceRunStateMachine
{
    protected ?MiningSmelterFurnaceRun $furnaceRun = null;

    public function __construct(?MiningSmelterFurnaceRun $furnaceRun = null)
    {
        $this->furnaceRun = $furnaceRun;
    }

    public function canTransition(FurnaceRunStatus $current, FurnaceRunStatus $target): bool
    {
        return $current->canTransitionTo($target);
    }

    public function transitionTo(FurnaceRunStatus $targetStatus): void
    {
        if (! $this->furnaceRun) {
            throw new InvalidMiningStateTransitionException('Instance MiningSmelterFurnaceRun belum dikonfigurasi dalam state machine.');
        }

        $this->transition($this->furnaceRun, $targetStatus);
    }

    public function transition(MiningSmelterFurnaceRun $furnaceRun, FurnaceRunStatus $targetStatus): void
    {
        $currentVal = strtoupper((string) ($furnaceRun->status ?? 'DRAFT'));
        $currentStatus = FurnaceRunStatus::tryFrom($currentVal) ?? FurnaceRunStatus::DRAFT;

        if ($currentStatus === $targetStatus) {
            return;
        }

        if (! $currentStatus->canTransitionTo($targetStatus)) {
            throw InvalidMiningStateTransitionException::forEntity(
                'MiningSmelterFurnaceRun',
                $currentStatus->value,
                $targetStatus->value,
                "Batch tanur {$furnaceRun->run_code} ({$currentStatus->value}) tidak dapat dialihkan ke {$targetStatus->value}."
            );
        }

        $this->assertTransitionGuard($furnaceRun, $currentStatus, $targetStatus);

        $furnaceRun->status = $targetStatus->value;
        $furnaceRun->save();
    }

    protected function assertTransitionGuard(
        MiningSmelterFurnaceRun $furnaceRun,
        FurnaceRunStatus $current,
        FurnaceRunStatus $target
    ): void {
        if ($target === FurnaceRunStatus::CHARGING && $furnaceRun->ore_feed_wmt <= 0.0) {
            throw InvalidMiningStateTransitionException::forEntity(
                'MiningSmelterFurnaceRun',
                $current->value,
                $target->value,
                'Pengisian tanur memerlukan kuantitas ore_feed_wmt > 0 ton.'
            );
        }

        if ($target === FurnaceRunStatus::SMELTING && $furnaceRun->coal_reductant_tons <= 0.0) {
            throw InvalidMiningStateTransitionException::forEntity(
                'MiningSmelterFurnaceRun',
                $current->value,
                $target->value,
                'Peleburan reduksi memerlukan reduktor batubara/kokas > 0 ton.'
            );
        }

        if ($target === FurnaceRunStatus::TAPPING && $furnaceRun->tapping_temperature_c < 1400.0) {
            throw InvalidMiningStateTransitionException::forEntity(
                'MiningSmelterFurnaceRun',
                $current->value,
                $target->value,
                'Suhu tanur belum mencapai ambang tapping minimum (1400°C).'
            );
        }

        if ($target === FurnaceRunStatus::COMPLETED && $furnaceRun->metal_output_tons <= 0.0) {
            throw InvalidMiningStateTransitionException::forEntity(
                'MiningSmelterFurnaceRun',
                $current->value,
                $target->value,
                'Penyelesaian run tanur memerlukan pencatatan perolehan produk logam > 0 ton.'
            );
        }
    }
}
