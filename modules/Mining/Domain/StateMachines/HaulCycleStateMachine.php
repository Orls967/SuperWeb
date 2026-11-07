<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\StateMachines;

use Modules\Mining\Domain\Enums\HaulCycleStatus;
use Modules\Mining\Domain\Exceptions\InvalidMiningStateTransitionException;
use Modules\Mining\Domain\Models\MiningHaulCycle;
use Modules\Mining\Domain\Models\MiningHaulCycleHistory;

final class HaulCycleStateMachine
{
    public function transition(MiningHaulCycle $cycle, HaulCycleStatus $targetStatus, ?string $actorId = null, ?string $notes = null): void
    {
        $currentStatus = $cycle->status instanceof HaulCycleStatus
            ? $cycle->status
            : HaulCycleStatus::from((string) $cycle->status);

        if (! $currentStatus->canTransitionTo($targetStatus)) {
            throw InvalidMiningStateTransitionException::forEntity(
                'MiningHaulCycle',
                $currentStatus->value,
                $targetStatus->value,
                "Siklus angkut truk berstatus {$currentStatus->value} tidak dapat beralih ke {$targetStatus->value}"
            );
        }

        $cycle->update([
            'status' => $targetStatus->value,
        ]);

        MiningHaulCycleHistory::create([
            'haul_cycle_id' => $cycle->id,
            'from_status' => $currentStatus->value,
            'to_status' => $targetStatus->value,
            'actor_id' => $actorId ?? 'system',
            'notes' => $notes,
            'recorded_at' => now(),
        ]);
    }
}
