<?php

declare(strict_types=1);

namespace Modules\Core\Domain\StateMachines;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\OutboxBusInterface;
use Modules\Core\Domain\Models\SimCheckpoint;
use Modules\Core\Domain\Models\SimRun;
use Modules\Core\Domain\Models\SimRunTransition;
use Modules\Core\Domain\StateMachines\Exceptions\InvalidSimulationTransitionException;

/**
 * State Machine formal untuk entitas SimulationRun (8 status).
 */
class SimulationRunStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    public const ALLOWED_TRANSITIONS = [
        SimulationRunStatus::DRAFT->value => [
            SimulationRunStatus::PROVISIONED->value,
            SimulationRunStatus::RUNNING->value, // Shortcut untuk run tanpa provisioner khusus
        ],
        SimulationRunStatus::PROVISIONED->value => [
            SimulationRunStatus::RUNNING->value,
        ],
        SimulationRunStatus::RUNNING->value => [
            SimulationRunStatus::PAUSED->value,
            SimulationRunStatus::CHECKPOINTED->value,
            SimulationRunStatus::COMPLETED->value,
            SimulationRunStatus::FAILED->value,
        ],
        SimulationRunStatus::PAUSED->value => [
            SimulationRunStatus::RUNNING->value,
            SimulationRunStatus::FAILED->value,
        ],
        SimulationRunStatus::CHECKPOINTED->value => [
            SimulationRunStatus::RUNNING->value,
            SimulationRunStatus::FAILED->value,
            SimulationRunStatus::COMPLETED->value,
        ],
        SimulationRunStatus::FAILED->value => [
            SimulationRunStatus::CHECKPOINTED->value, // Rollback ke checkpoint terakhir
            SimulationRunStatus::ARCHIVED->value,
        ],
        SimulationRunStatus::COMPLETED->value => [
            SimulationRunStatus::RUNNING->value, // Melanjutkan (resume) simulasi dengan perpanjangan durasi hari
            SimulationRunStatus::ARCHIVED->value,
        ],
        SimulationRunStatus::ARCHIVED->value => [],
    ];

    public function __construct(
        protected ?OutboxBusInterface $outboxBus = null,
    ) {}

    /**
     * Memeriksa apakah transisi dari status asal ke status tujuan diizinkan.
     */
    public function canTransition(string $from, string $to): bool
    {
        $allowed = self::ALLOWED_TRANSITIONS[$from] ?? [];

        return in_array($to, $allowed, true);
    }

    /**
     * Mengeksekusi transisi status dengan guard ketat dan mencatat riwayat append-only.
     *
     * @param  array<string, mixed>  $metadata
     *
     * @throws InvalidSimulationTransitionException
     */
    public function transition(
        SimRun $run,
        SimulationRunStatus $targetStatus,
        string $reason,
        ?string $actorId = null,
        ?string $actorRole = null,
        array $metadata = []
    ): SimRun {
        $fromStatus = $run->status;
        $toStatus = $targetStatus->value;

        if ($fromStatus === $toStatus) {
            return $run;
        }

        if (! $this->canTransition($fromStatus, $toStatus)) {
            throw new InvalidSimulationTransitionException(
                $fromStatus,
                $toStatus,
                'Transisi tidak terdaftar dalam matriks status.'
            );
        }

        // Evaluasi Guard
        $this->evaluateGuards($run, $fromStatus, $targetStatus, $reason, $metadata);

        DB::transaction(function () use ($run, $fromStatus, $toStatus, $reason, $actorId, $actorRole, $metadata): void {
            $run->status = $toStatus;
            if ($toStatus === SimulationRunStatus::COMPLETED->value) {
                $run->completed_at = now();
            }
            $run->save();

            SimRunTransition::create([
                'run_id' => $run->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'reason' => $reason,
                'actor_id' => $actorId ?? (string) auth()->id(),
                'actor_role' => $actorRole ?? (auth()->user()?->roles?->first()?->name ?? 'system'),
                'metadata' => $metadata,
                'created_at' => now(),
            ]);
        });

        // Terbitkan event perubahan status simulasi
        $this->dispatchStateChangedEvent($run, $fromStatus, $toStatus, $reason);

        return $run;
    }

    /**
     * Evaluasi guard spesifik tiap transisi.
     *
     * @param  array<string, mixed>  $metadata
     */
    protected function evaluateGuards(
        SimRun $run,
        string $fromStatus,
        SimulationRunStatus $targetStatus,
        string $reason,
        array $metadata
    ): void {
        // Guard 1: Transisi ke FAILED wajib mencantumkan alasan bermakna
        if ($targetStatus === SimulationRunStatus::FAILED && trim($reason) === '') {
            throw new InvalidSimulationTransitionException($fromStatus, $targetStatus->value, 'Alasan kegagalan wajib diisi.');
        }

        // Guard 2: Transisi FAILED -> CHECKPOINTED (pemulihan) wajib memiliki checkpoint valid
        if ($fromStatus === SimulationRunStatus::FAILED->value && $targetStatus === SimulationRunStatus::CHECKPOINTED) {
            $hasCheckpoint = SimCheckpoint::where('run_id', $run->id)->exists();
            if (! $hasCheckpoint) {
                throw new InvalidSimulationTransitionException(
                    $fromStatus,
                    $targetStatus->value,
                    'Pemulihan dari status gagal memerlukan setidaknya satu checkpoint valid yang tersimpan.'
                );
            }
        }

        // Guard 3: Transisi RUNNING -> CHECKPOINTED wajib memiliki chain_hash tercatat
        if ($targetStatus === SimulationRunStatus::CHECKPOINTED && empty($run->chain_hash) && empty($metadata['chain_hash'])) {
            throw new InvalidSimulationTransitionException(
                $fromStatus,
                $targetStatus->value,
                'Penulisan checkpoint memerlukan rekaman hash rantai yang valid.'
            );
        }
    }

    protected function dispatchStateChangedEvent(SimRun $run, string $fromStatus, string $toStatus, string $reason): void
    {
        if ($this->outboxBus === null) {
            return;
        }

        try {
            $this->outboxBus->publish(
                'core.simulation.run_state_changed.v1',
                [
                    'run_id' => $run->id,
                    'name' => $run->name,
                    'from_status' => $fromStatus,
                    'to_status' => $toStatus,
                    'current_day' => $run->current_day,
                    'reason' => $reason,
                    'timestamp' => now()->toIso8601String(),
                ],
                "core:sim:state_change:{$run->id}:{$toStatus}:{$run->current_day}"
            );
        } catch (\Throwable) {
            // Outbox bus kegagalan tidak membatalkan transisi lokal
        }
    }
}
