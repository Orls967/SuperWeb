<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\StateMachines;

use Modules\Mining\Domain\Enums\MinePlanStatus;
use Modules\Mining\Domain\Exceptions\InvalidMiningStateTransitionException;
use Modules\Mining\Domain\Models\MiningPlan;

final class MinePlanStateMachine
{
    public function transition(MiningPlan $plan, MinePlanStatus $targetStatus, ?string $actorId = null, ?string $notes = null): void
    {
        $currentStatus = $plan->status instanceof MinePlanStatus
            ? $plan->status
            : MinePlanStatus::from((string) $plan->status);

        if (! $currentStatus->canTransitionTo($targetStatus)) {
            throw InvalidMiningStateTransitionException::forEntity(
                'MiningPlan',
                $currentStatus->value,
                $targetStatus->value,
                "Rencana tambang berstatus {$currentStatus->value} tidak dapat langsung dialihkan ke {$targetStatus->value}"
            );
        }

        $plan->update([
            'status' => $targetStatus->value,
        ]);
    }
}
