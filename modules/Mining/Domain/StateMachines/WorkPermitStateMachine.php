<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\StateMachines;

use Modules\Mining\Domain\Enums\WorkPermitStatus;
use Modules\Mining\Domain\Exceptions\InvalidMiningStateTransitionException;
use Modules\Mining\Domain\Models\MiningWorkPermit;

/**
 * State Machine Izin Kerja Risiko Tinggi (Permit to Work - PTW) HSE (D94-5).
 * Mengawal 8 status: requested -> risk_assessed -> approved -> active -> suspended -> expired -> closed -> rejected.
 */
final class WorkPermitStateMachine
{
    public function __construct(
        private ?MiningWorkPermit $permitModel = null
    ) {}

    public function transitionTo(WorkPermitStatus $targetStatus, ?string $notes = null): void
    {
        if (! $this->permitModel) {
            throw new \RuntimeException('MiningWorkPermit model tidak disetel dalam state machine.');
        }

        $this->transition($this->permitModel, $targetStatus, notes: $notes);
    }

    public function transition(
        MiningWorkPermit $permit,
        WorkPermitStatus $targetStatus,
        ?string $actorId = null,
        ?string $notes = null
    ): void {
        $currentStatus = $permit->statusEnum();

        if ($currentStatus === $targetStatus) {
            return;
        }

        if (! $currentStatus->canTransitionTo($targetStatus)) {
            throw InvalidMiningStateTransitionException::forEntity(
                'MiningWorkPermit',
                $currentStatus->value,
                $targetStatus->value,
                "Izin kerja {$permit->permit_number} ({$currentStatus->value}) tidak dapat dialihkan ke {$targetStatus->value}."
            );
        }

        $this->assertTransitionGuard($permit, $currentStatus, $targetStatus);

        $permit->update([
            'status' => $targetStatus,
        ]);
    }

    public function advanceAlongPath(
        MiningWorkPermit $permit,
        array $steps,
        ?string $actorId = null,
        ?string $notes = null
    ): void {
        foreach ($steps as $step) {
            $this->transition($permit, $step, $actorId, $notes);
        }
    }

    private function assertTransitionGuard(
        MiningWorkPermit $permit,
        WorkPermitStatus $from,
        WorkPermitStatus $to
    ): void {
        if ($to === WorkPermitStatus::ACTIVE && now()->greaterThan($permit->valid_to)) {
            throw InvalidMiningStateTransitionException::forEntity(
                'MiningWorkPermit',
                $from->value,
                $to->value,
                "Izin kerja {$permit->permit_number} telah melewati masa berlaku valid_to dan ditolak aktivasi."
            );
        }

        if ($to === WorkPermitStatus::ACTIVE && $from === WorkPermitStatus::SUSPENDED && empty($permit->capa_ref)) {
            throw InvalidMiningStateTransitionException::forEntity(
                'MiningWorkPermit',
                $from->value,
                $to->value,
                "Izin kerja {$permit->permit_number} yang ditangguhkan memerlukan verifikasi tindakan perbaikan (CAPA) sebelum dapat diaktifkan kembali."
            );
        }
    }
}
