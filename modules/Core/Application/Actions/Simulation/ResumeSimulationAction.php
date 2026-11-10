<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\Simulation;

use Modules\Core\Domain\Models\SimRun;
use Modules\Core\Domain\StateMachines\SimulationRunStateMachine;
use Modules\Core\Domain\StateMachines\SimulationRunStatus;

final class ResumeSimulationAction
{
    public function __construct(
        private readonly SimulationRunStateMachine $stateMachine,
    ) {}

    public function execute(SimRun $run, string $reason = 'Melanjutkan eksekusi simulasi', ?string $actorId = null): void
    {
        $this->stateMachine->transition(
            $run,
            SimulationRunStatus::RUNNING,
            $reason,
            actorId: $actorId ?? 'operator',
            actorRole: 'sim_operator'
        );
    }
}
