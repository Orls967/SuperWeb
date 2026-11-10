<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Simulation;

use Modules\Core\Domain\Models\SimCheckpoint;
use Modules\Core\Domain\Models\SimRun;
use Modules\Core\Domain\Models\SimRunTransition;
use Modules\Core\Domain\StateMachines\SimulationRunStateMachine;
use Modules\Core\Domain\StateMachines\SimulationRunStatus;

/**
 * Layanan pengarsipan dingin (cold storage archiving) untuk sesi simulasi yang telah tuntas.
 */
final class SimulationRunArchiveService
{
    public function __construct(
        private readonly SimulationRunStateMachine $stateMachine,
    ) {}

    /**
     * @return array{
     *     run_id: string,
     *     archived_at: string,
     *     archive_manifest: array<string, mixed>
     * }
     */
    public function archiveCompletedRun(SimRun $run, string $actorId = 'system'): array
    {
        $this->stateMachine->transition(
            $run,
            SimulationRunStatus::ARCHIVED,
            'Pengarsipan sesi simulasi ke cold storage manifest',
            actorId: $actorId,
            actorRole: 'sim_operator'
        );

        $checkpoints = SimCheckpoint::where('run_id', $run->id)->get();
        $transitions = SimRunTransition::where('run_id', $run->id)->get();

        $manifest = [
            'run_id' => $run->id,
            'name' => $run->name,
            'seed' => $run->seed,
            'total_days' => $run->total_days,
            'final_hash' => $run->chain_hash,
            'checkpoints_count' => $checkpoints->count(),
            'transitions_count' => $transitions->count(),
            'archived_at' => now()->toIso8601String(),
        ];

        return [
            'run_id' => $run->id,
            'archived_at' => now()->toIso8601String(),
            'archive_manifest' => $manifest,
        ];
    }
}
