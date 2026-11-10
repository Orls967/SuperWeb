<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Simulation;

use Modules\Core\Domain\Models\SimCheckpoint;
use Modules\Core\Domain\Models\SimRun;
use Modules\Core\Domain\Models\SimRunTransition;
use Modules\Core\Domain\Models\SimTwinState;

/**
 * Layanan pembersihan data historis dan pemeliharaan partisi simulasi.
 */
final class SimulationDataPrunerService
{
    /**
     * Membersihkan rekaman sementara sesi sandbox yang telah usang (> 30 hari).
     */
    public function pruneExpiredSandboxData(int $retentionDays = 30): array
    {
        $cutoff = now()->subDays($retentionDays);

        $sandboxRunIds = SimRun::where('mode', 'sandbox')
            ->where('created_at', '<', $cutoff)
            ->pluck('id');

        $deletedTransitions = SimRunTransition::whereIn('run_id', $sandboxRunIds)->delete();
        $deletedCheckpoints = SimCheckpoint::whereIn('run_id', $sandboxRunIds)->delete();
        $deletedRuns = SimRun::whereIn('id', $sandboxRunIds)->delete();

        $deletedSandboxTwinStates = SimTwinState::where('is_sandbox', true)
            ->where('created_at', '<', $cutoff)
            ->delete();

        return [
            'deleted_runs' => $deletedRuns,
            'deleted_checkpoints' => $deletedCheckpoints,
            'deleted_transitions' => $deletedTransitions,
            'deleted_twin_states' => $deletedSandboxTwinStates,
        ];
    }
}
