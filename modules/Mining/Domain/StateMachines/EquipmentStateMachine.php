<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\StateMachines;

use Modules\Mining\Domain\Enums\EquipmentStatus;
use Modules\Mining\Domain\Exceptions\InvalidMiningStateTransitionException;
use Modules\Mining\Domain\Models\MiningEquipment;

final class EquipmentStateMachine
{
    public function transition(MiningEquipment $equipment, EquipmentStatus $targetStatus, ?string $actorId = null, ?string $notes = null): void
    {
        $currentStatus = $equipment->status instanceof EquipmentStatus
            ? $equipment->status
            : EquipmentStatus::tryFrom((string) $equipment->status) ?? EquipmentStatus::AVAILABLE;

        if (! $currentStatus->canTransitionTo($targetStatus)) {
            throw InvalidMiningStateTransitionException::forEntity(
                'MiningEquipment',
                $currentStatus->value,
                $targetStatus->value,
                "Unit alat berat {$equipment->equipment_code} berstatus {$currentStatus->value} tidak dapat beralih ke {$targetStatus->value}"
            );
        }

        $equipment->update([
            'status' => $targetStatus->value,
        ]);
    }
}
