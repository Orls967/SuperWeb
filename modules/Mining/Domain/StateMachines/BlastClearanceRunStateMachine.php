<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\StateMachines;

use Carbon\CarbonImmutable;
use Modules\Mining\Domain\Enums\BlastClearanceStatus;
use Modules\Mining\Domain\Exceptions\InvalidMiningStateTransitionException;
use Modules\Mining\Domain\Models\MiningBlastClearanceRun;

/**
 * State Machine Peledakan Tambang & Interlock Keselamatan (Fase 119.2).
 * Mengawal 8 status siklus hidup peledakan perimeter dari DRAFT hingga RESOLVED.
 */
class BlastClearanceRunStateMachine
{
    protected ?MiningBlastClearanceRun $run = null;

    public function __construct(?MiningBlastClearanceRun $run = null)
    {
        $this->run = $run;
    }

    public function canTransition(BlastClearanceStatus $current, BlastClearanceStatus $target): bool
    {
        return $current->canTransitionTo($target);
    }

    public function transitionTo(
        BlastClearanceStatus $targetStatus,
        ?string $officer = null,
        ?string $notes = null
    ): void {
        if (! $this->run) {
            throw new InvalidMiningStateTransitionException('Instance MiningBlastClearanceRun belum dikonfigurasi dalam state machine.');
        }

        $this->transition($this->run, $targetStatus, $officer, $notes);
    }

    public function transition(
        MiningBlastClearanceRun $run,
        BlastClearanceStatus $targetStatus,
        ?string $officer = null,
        ?string $notes = null
    ): void {
        $currentStatus = $run->statusEnum();

        if ($currentStatus === $targetStatus) {
            return;
        }

        if (! $currentStatus->canTransitionTo($targetStatus)) {
            throw InvalidMiningStateTransitionException::forEntity(
                'MiningBlastClearanceRun',
                $currentStatus->value,
                $targetStatus->value,
                "Prosedur peledakan {$run->run_code} ({$currentStatus->value}) tidak dapat dialihkan ke {$targetStatus->value}."
            );
        }

        $this->assertTransitionGuard($run, $currentStatus, $targetStatus);

        $now = CarbonImmutable::now();
        $updates = [
            'status' => $targetStatus->value,
        ];

        if ($officer !== null) {
            $updates['officer_in_charge'] = $officer;
        }

        if ($notes !== null) {
            $updates['safety_notes'] = $notes;
        }

        if ($targetStatus === BlastClearanceStatus::SIREN_ACTIVE) {
            $updates['siren_activated_at'] = $now;
        } elseif ($targetStatus === BlastClearanceStatus::ALL_CLEAR) {
            $updates['cleared_at'] = $now;
        } elseif ($targetStatus === BlastClearanceStatus::DETONATED) {
            $updates['detonated_at'] = $now;
        } elseif ($targetStatus === BlastClearanceStatus::MUCKPILE_INSPECTED) {
            $updates['inspected_at'] = $now;
        }

        $run->update($updates);
    }

    private function assertTransitionGuard(
        MiningBlastClearanceRun $run,
        BlastClearanceStatus $current,
        BlastClearanceStatus $target
    ): void {
        if ($target === BlastClearanceStatus::ALL_CLEAR) {
            if (! $run->isSterile()) {
                throw InvalidMiningStateTransitionException::forEntity(
                    'MiningBlastClearanceRun',
                    $current->value,
                    $target->value,
                    "Perimeter radius bahaya peledakan ({$run->safety_radius_meters}m) belum steril. Terdeteksi {$run->personnel_detected_count} personel dan {$run->equipment_detected_count} unit alat berat di dalam zona bahaya."
                );
            }
        }

        if ($target === BlastClearanceStatus::DETONATED) {
            if ($run->cleared_at === null) {
                throw InvalidMiningStateTransitionException::forEntity(
                    'MiningBlastClearanceRun',
                    $current->value,
                    $target->value,
                    'Detonasi ditolak: Verifikasi perimeter ALL_CLEAR belum pernah disahkan oleh petugas keselamatan peledakan.'
                );
            }
        }
    }
}
