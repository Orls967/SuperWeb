<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\StateMachines;

use Modules\Mining\Domain\Enums\OfftakeShipmentStatus;
use Modules\Mining\Domain\Exceptions\InvalidMiningStateTransitionException;
use Modules\Mining\Domain\Models\MiningOfftakeShipment;

/**
 * State Machine Pengapalan dan Eksekusi Kontrak Offtake (D94-7).
 * Mengawal 10 status: contracted -> nominated -> lc_opened -> loading -> provisional_invoiced -> final_assayed -> final_invoiced -> settled -> disputed -> cancelled.
 */
final class OfftakeShipmentStateMachine
{
    public function transition(
        MiningOfftakeShipment $shipment,
        OfftakeShipmentStatus $targetStatus,
        ?string $actorId = null,
        ?string $notes = null
    ): void {
        $currentStatus = $shipment->status;

        if ($currentStatus === $targetStatus) {
            return;
        }

        if (! $currentStatus->canTransitionTo($targetStatus)) {
            throw InvalidMiningStateTransitionException::forEntity(
                'MiningOfftakeShipment',
                $currentStatus->value,
                $targetStatus->value,
                "Pengapalan {$shipment->shipment_number} ({$currentStatus->value}) tidak dapat dialihkan ke {$targetStatus->value}."
            );
        }

        $this->assertTransitionGuard($shipment, $currentStatus, $targetStatus);

        $updates = ['status' => $targetStatus];
        if ($targetStatus === OfftakeShipmentStatus::CANCELLED && $notes !== null) {
            $updates['diversion_reason'] = $notes;
        }

        $shipment->update($updates);
    }

    public function advanceAlongPath(
        MiningOfftakeShipment $shipment,
        array $steps,
        ?string $actorId = null,
        ?string $notes = null
    ): void {
        foreach ($steps as $step) {
            $this->transition($shipment, $step, $actorId, $notes);
        }
    }

    private function assertTransitionGuard(
        MiningOfftakeShipment $shipment,
        OfftakeShipmentStatus $from,
        OfftakeShipmentStatus $to
    ): void {
        if ($to === OfftakeShipmentStatus::LOADING && $shipment->contract->lc_required && empty($shipment->lc_ref)) {
            throw InvalidMiningStateTransitionException::forEntity(
                'MiningOfftakeShipment',
                $from->value,
                $to->value,
                "Pengapalan {$shipment->shipment_number} mensyaratkan Letter of Credit dibuka sebelum pemuatan kargo dimulai."
            );
        }

        if ($to === OfftakeShipmentStatus::LOADING && $shipment->is_export && $shipment->export_prohibited_diverted) {
            throw InvalidMiningStateTransitionException::forEntity(
                'MiningOfftakeShipment',
                $from->value,
                $to->value,
                "Pengapalan {$shipment->shipment_number} terkena larangan ekspor bijih mentah dan wajib dialihkan ke smelter domestik."
            );
        }
    }
}
