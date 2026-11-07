<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\StateMachines;

use Modules\Mining\Domain\Enums\BargeShipmentStatus;
use Modules\Mining\Domain\Exceptions\InvalidMiningStateTransitionException;
use Modules\Mining\Domain\Models\BargeShipment;

/**
 * State Machine Pengapalan Tongkang Curah (Fase 119.5).
 * Mengawal 8 status rantai kustodi logistik laut tongkang dari BERTHED hingga SETTLED.
 */
class BargeShipmentStateMachine
{
    protected ?BargeShipment $shipment = null;

    public function __construct(?BargeShipment $shipment = null)
    {
        $this->shipment = $shipment;
    }

    public function canTransition(BargeShipmentStatus $current, BargeShipmentStatus $target): bool
    {
        return $current->canTransitionTo($target);
    }

    public function transitionTo(
        BargeShipmentStatus $targetStatus
    ): void {
        if (! $this->shipment) {
            throw new InvalidMiningStateTransitionException('Instance BargeShipment belum dikonfigurasi dalam state machine.');
        }

        $this->transition($this->shipment, $targetStatus);
    }

    public function transition(
        BargeShipment $shipment,
        BargeShipmentStatus $targetStatus
    ): void {
        $currentVal = strtoupper((string) ($shipment->status ?? 'BERTHED'));
        $currentStatus = BargeShipmentStatus::tryFrom($currentVal) ?? BargeShipmentStatus::BERTHED;

        if ($currentStatus === $targetStatus) {
            return;
        }

        if (! $currentStatus->canTransitionTo($targetStatus)) {
            throw InvalidMiningStateTransitionException::forEntity(
                'BargeShipment',
                $currentStatus->value,
                $targetStatus->value,
                "Pengapalan tongkang {$shipment->barge_code} ({$currentStatus->value}) tidak dapat dialihkan ke {$targetStatus->value}."
            );
        }

        $this->assertTransitionGuard($shipment, $currentStatus, $targetStatus);

        $shipment->update([
            'status' => $targetStatus->value,
        ]);
    }

    private function assertTransitionGuard(
        BargeShipment $shipment,
        BargeShipmentStatus $current,
        BargeShipmentStatus $target
    ): void {
        if ($target === BargeShipmentStatus::DRAFT_SURVEYED) {
            if ((float) $shipment->weighbridge_tonnage <= 0.0) {
                throw InvalidMiningStateTransitionException::forEntity(
                    'BargeShipment',
                    $current->value,
                    $target->value,
                    'Survei draf tidak dapat disahkan sebelum data tonase jembatan timbang tercatat lengkap.'
                );
            }
        }
    }
}
