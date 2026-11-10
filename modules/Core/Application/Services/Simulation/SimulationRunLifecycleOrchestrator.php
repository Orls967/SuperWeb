<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Simulation;

use Modules\Core\Application\Actions\Simulation\CreateSimulationRunAction;
use Modules\Core\Application\Actions\Simulation\PauseSimulationAction;
use Modules\Core\Application\Actions\Simulation\ResumeSimulationAction;
use Modules\Core\Contracts\SimulationRunner;
use Modules\Core\Domain\Models\SimRun;

/**
 * Orkestrator terpusat antarmuka kontrol siklus hidup sesi simulasi.
 */
final class SimulationRunLifecycleOrchestrator
{
    public function __construct(
        private readonly SimulationRunner $runner,
        private readonly CreateSimulationRunAction $createAction,
        private readonly PauseSimulationAction $pauseAction,
        private readonly ResumeSimulationAction $resumeAction,
        private readonly SimulationConcurrencyLockService $lockService,
        private readonly SimulationAuditLogPublisherService $auditLogger,
    ) {}

    public function initializeAndRun(
        string $name,
        int $days,
        int $seed,
        string $mode = 'live',
        ?int $actorId = null,
    ): SimRun {
        $run = $this->createAction->execute([
            'name' => $name,
            'days' => $days,
            'seed' => $seed,
            'mode' => $mode,
            'created_by' => $actorId,
        ]);

        $this->auditLogger->logOperation($run, 'created', (string) ($actorId ?? 'system'), 'sim_operator', [
            'days' => $days,
            'seed' => $seed,
            'mode' => $mode,
        ]);

        // Eksekusi simulasi
        $this->runner->start($name, $days, $seed, $mode);

        return $run->fresh();
    }

    public function pauseRun(SimRun $run, string $reason, string $actorId): void
    {
        $this->pauseAction->execute($run, $reason, $actorId);
        $this->lockService->releaseLock($run->id);

        $this->auditLogger->logOperation($run, 'paused', $actorId, 'sim_operator', [
            'reason' => $reason,
            'paused_day' => $run->current_day,
        ]);
    }

    public function resumeRun(SimRun $run, string $reason, string $actorId): void
    {
        $this->resumeAction->execute($run, $reason, $actorId);
        $this->runner->resume($run->id);

        $this->auditLogger->logOperation($run, 'resumed', $actorId, 'sim_operator', [
            'reason' => $reason,
            'resumed_day' => $run->current_day,
        ]);
    }
}
