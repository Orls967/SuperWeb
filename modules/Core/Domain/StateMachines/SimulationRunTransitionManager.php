<?php

declare(strict_types=1);

namespace Modules\Core\Domain\StateMachines;

use Illuminate\Support\Collection;
use Modules\Core\Domain\Models\SimRun;
use Modules\Core\Domain\Models\SimRunTransition;
use Modules\Core\Domain\StateMachines\Exceptions\InvalidSimulationTransitionException;

/**
 * Manajer audit dan eksekusi transisi status sesi simulasi.
 * Menegakkan aturan transisi sah, mencatat jejak audit append-only ke `sim_run_transitions`.
 */
final class SimulationRunTransitionManager
{
    public function __construct(
        private readonly SimulationRunStateMachine $stateMachine,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function transition(
        SimRun $run,
        SimulationRunStatus $targetStatus,
        string $reason,
        string $actorId = 'system',
        string $actorRole = 'sim_operator',
        array $metadata = [],
    ): SimRunTransition {
        $fromStatus = SimulationRunStatus::from($run->status);

        if (! $this->stateMachine->canTransition($fromStatus, $targetStatus)) {
            throw new InvalidSimulationTransitionException(
                "Transisi status simulasi ilegal dari '{$fromStatus->value}' ke '{$targetStatus->value}'."
            );
        }

        $run->status = $targetStatus->value;
        if ($targetStatus === SimulationRunStatus::COMPLETED && $run->completed_at === null) {
            $run->completed_at = now();
        }
        $run->save();

        return SimRunTransition::create([
            'run_id' => $run->id,
            'from_status' => $fromStatus->value,
            'to_status' => $targetStatus->value,
            'reason' => $reason,
            'actor_identifier' => $actorId,
            'actor_role' => $actorRole,
            'metadata' => $metadata,
        ]);
    }

    /**
     * @return Collection<int, SimRunTransition>
     */
    public function getTransitionHistory(string $runId): Collection
    {
        return SimRunTransition::where('run_id', $runId)
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Memverifikasi bahwa seluruh riwayat transisi berkesinambungan tanpa ada status lompatan hantu.
     */
    public function verifyTransitionIntegrity(string $runId): bool
    {
        $transitions = $this->getTransitionHistory($runId);
        if ($transitions->isEmpty()) {
            return true;
        }

        $expectedFrom = null;

        foreach ($transitions as $idx => $t) {
            if ($idx === 0) {
                if ($t->from_status !== SimulationRunStatus::DRAFT->value) {
                    return false;
                }
            } else {
                if ($t->from_status !== $expectedFrom) {
                    return false;
                }
            }
            $expectedFrom = $t->to_status;
        }

        return true;
    }
}
