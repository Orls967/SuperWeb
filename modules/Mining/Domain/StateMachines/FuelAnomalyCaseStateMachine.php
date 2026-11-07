<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\StateMachines;

use Modules\Mining\Domain\Enums\FuelAnomalyCaseStatus;
use Modules\Mining\Domain\Exceptions\InvalidMiningStateTransitionException;
use Modules\Mining\Domain\Models\MiningFuelAnomalyCase;
use Modules\Mining\Domain\Models\MiningFuelAnomalyHistory;

final class FuelAnomalyCaseStateMachine
{
    public function transition(MiningFuelAnomalyCase $case, FuelAnomalyCaseStatus $targetStatus, ?string $actorId = null, ?string $notes = null): void
    {
        $currentStatus = $case->status instanceof FuelAnomalyCaseStatus
            ? $case->status
            : FuelAnomalyCaseStatus::from((string) $case->status);

        if (! $currentStatus->canTransitionTo($targetStatus)) {
            throw InvalidMiningStateTransitionException::forEntity(
                'MiningFuelAnomalyCase',
                $currentStatus->value,
                $targetStatus->value,
                "Kasus anomali BBM berstatus {$currentStatus->value} tidak dapat beralih ke {$targetStatus->value}"
            );
        }

        $case->update([
            'status' => $targetStatus->value,
        ]);

        MiningFuelAnomalyHistory::create([
            'case_id' => $case->id,
            'from_status' => $currentStatus->value,
            'to_status' => $targetStatus->value,
            'actor_id' => $actorId ?? 'system',
            'notes' => $notes,
        ]);
    }
}
