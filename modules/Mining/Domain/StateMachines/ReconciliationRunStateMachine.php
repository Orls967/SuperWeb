<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\StateMachines;

use Modules\Mining\Domain\Enums\ReconciliationRunStatus;
use Modules\Mining\Domain\Exceptions\InvalidMiningStateTransitionException;
use Modules\Mining\Domain\Models\MiningReconciliationRun;

/**
 * State Machine Rekonsiliasi Neraca Material & Stockpile Tambang (D94-1).
 * Mengawal 8 status: open -> collecting -> balancing -> variance_detected -> investigating -> approved -> locked -> reopened.
 */
final class ReconciliationRunStateMachine
{
    public function __construct(
        private ?MiningReconciliationRun $runModel = null
    ) {}

    public function transitionTo(ReconciliationRunStatus $targetStatus, ?string $notes = null): void
    {
        if (! $this->runModel) {
            throw new \RuntimeException('MiningReconciliationRun model tidak disetel dalam state machine.');
        }

        $this->transition($this->runModel, $targetStatus, notes: $notes);
    }

    public function transition(
        MiningReconciliationRun $run,
        ReconciliationRunStatus $targetStatus,
        ?string $actorId = null,
        ?string $notes = null
    ): void {
        $currentStatus = $run->status;

        if ($currentStatus === $targetStatus) {
            return;
        }

        if (! $currentStatus->canTransitionTo($targetStatus)) {
            throw InvalidMiningStateTransitionException::forEntity(
                'MiningReconciliationRun',
                $currentStatus->value,
                $targetStatus->value,
                "Rekonsiliasi {$run->run_number} berstatus {$currentStatus->value} tidak dapat dialihkan ke {$targetStatus->value} (Aktor: ".($actorId ?? 'system').', Catatan: '.($notes ?? '-').')'
            );
        }

        $this->assertTransitionGuard($run, $currentStatus, $targetStatus);

        $updates = ['status' => $targetStatus];
        if ($targetStatus === ReconciliationRunStatus::APPROVED) {
            $updates['approved_at'] = now();
        } elseif ($targetStatus === ReconciliationRunStatus::LOCKED) {
            $updates['locked_at'] = now();
        }

        if ($notes !== null) {
            $updates['investigation_notes'] = $run->investigation_notes
                ? $run->investigation_notes."\n[".now()->toIso8601String().'] '.$notes
                : '['.now()->toIso8601String().'] '.$notes;
        }

        $run->update($updates);
    }

    public function advanceAlongPath(
        MiningReconciliationRun $run,
        array $steps,
        ?string $actorId = null,
        ?string $notes = null
    ): void {
        foreach ($steps as $step) {
            $this->transition($run, $step, $actorId, $notes);
        }
    }

    private function assertTransitionGuard(
        MiningReconciliationRun $run,
        ReconciliationRunStatus $from,
        ReconciliationRunStatus $to
    ): void {
        if ($to === ReconciliationRunStatus::APPROVED && $run->variance_bps > 500 && empty($run->investigation_notes)) {
            throw InvalidMiningStateTransitionException::forEntity(
                'MiningReconciliationRun',
                $from->value,
                $to->value,
                "Rekonsiliasi {$run->run_number} memiliki deviasi > 500 bps ({$run->variance_bps} bps) dan wajib memiliki catatan investigasi sebelum disetujui."
            );
        }

        if ($to === ReconciliationRunStatus::LOCKED && $from !== ReconciliationRunStatus::APPROVED) {
            throw InvalidMiningStateTransitionException::forEntity(
                'MiningReconciliationRun',
                $from->value,
                $to->value,
                "Rekonsiliasi {$run->run_number} hanya dapat dikunci setelah disetujui."
            );
        }
    }
}
