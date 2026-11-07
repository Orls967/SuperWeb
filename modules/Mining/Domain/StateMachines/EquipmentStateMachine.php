<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\StateMachines;

use Modules\Mining\Domain\Enums\EquipmentStatus;
use Modules\Mining\Domain\Exceptions\InvalidMiningStateTransitionException;
use Modules\Mining\Domain\Models\MiningEquipment;

/**
 * State Machine Ketersediaan & Pemeliharaan Alat Berat Tambang (D93-3).
 * Mengawal 8 status: available -> assigned -> operating -> standby -> breakdown -> under_maintenance -> returned_to_service -> retired.
 */
final class EquipmentStateMachine
{
    public function transition(MiningEquipment $equipment, EquipmentStatus $targetStatus, ?string $actorId = null, ?string $notes = null): void
    {
        $currentStatus = $this->resolveCurrentStatus($equipment);

        if ($currentStatus === $targetStatus) {
            return;
        }

        if (! $currentStatus->canTransitionTo($targetStatus)) {
            throw InvalidMiningStateTransitionException::forEntity(
                'MiningEquipment',
                $currentStatus->value,
                $targetStatus->value,
                "Unit alat berat {$equipment->equipment_code} berstatus {$currentStatus->value} tidak dapat beralih ke {$targetStatus->value} (Aktor: ".($actorId ?? 'system').', Catatan: '.($notes ?? '-').')'
            );
        }

        $equipment->update([
            'status' => $targetStatus->value,
        ]);
    }

    /**
     * Memindahkan unit ke status pemeliharaan bengkel melalui jalur sah (breakdown -> under_maintenance).
     */
    public function transitionToMaintenance(MiningEquipment $equipment, ?string $actorId = null, ?string $notes = null): void
    {
        $current = $this->resolveCurrentStatus($equipment);

        if ($current === EquipmentStatus::UNDER_MAINTENANCE) {
            return;
        }

        if (in_array($current, [EquipmentStatus::AVAILABLE, EquipmentStatus::ASSIGNED, EquipmentStatus::OPERATING, EquipmentStatus::STANDBY], true)) {
            $this->transition($equipment, EquipmentStatus::BREAKDOWN, $actorId, $notes);
        }

        $this->transition($equipment, EquipmentStatus::UNDER_MAINTENANCE, $actorId, $notes);
    }

    /**
     * Mengembalikan unit dari pemeliharaan bengkel ke ketersediaan penuh (returned_to_service -> available).
     */
    public function restoreFromMaintenance(MiningEquipment $equipment, ?string $actorId = null, ?string $notes = null): void
    {
        $current = $this->resolveCurrentStatus($equipment);

        if ($current === EquipmentStatus::UNDER_MAINTENANCE) {
            $this->transition($equipment, EquipmentStatus::RETURNED_TO_SERVICE, $actorId, $notes);
            $this->transition($equipment, EquipmentStatus::AVAILABLE, $actorId, $notes);
        } elseif ($current === EquipmentStatus::RETURNED_TO_SERVICE) {
            $this->transition($equipment, EquipmentStatus::AVAILABLE, $actorId, $notes);
        }
    }

    public function resolveCurrentStatus(MiningEquipment $equipment): EquipmentStatus
    {
        return $equipment->status instanceof EquipmentStatus
            ? $equipment->status
            : (EquipmentStatus::tryFrom((string) $equipment->status) ?? EquipmentStatus::AVAILABLE);
    }
}
