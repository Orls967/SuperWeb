<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Simulation;

use Modules\Core\Application\Actions\Simulation\DiffSimulationRunsAction;
use Modules\Core\Domain\Algorithms\Simulation\Verifiers\HashChainContinuityVerifier;
use Modules\Core\Domain\Models\SimCheckpoint;
use Modules\Core\Domain\Models\SimRun;
use Modules\Core\Domain\ValueObjects\Simulation\DivergencePoint;

/**
 * Engine audit determinisme kernel simulasi.
 * Memeriksa integritas rantai hash, kontinuitas checkpoint harian, dan deteksi dini penyimpangan antar run.
 */
final class SimulationDeterministicAuditEngine
{
    public function __construct(
        private readonly DiffSimulationRunsAction $diffAction,
        private readonly HashChainContinuityVerifier $continuityVerifier,
    ) {}

    /**
     * Memeriksa integritas penuh sebuah sesi simulasi.
     *
     * @return array{
     *     run_id: string,
     *     status: string,
     *     valid: bool,
     *     total_checkpoints: int,
     *     chain_continuous: bool,
     *     error_message: string|null
     * }
     */
    public function auditRun(SimRun $run): array
    {
        $checkpoints = SimCheckpoint::where('run_id', $run->id)
            ->orderBy('day_number', 'asc')
            ->get();

        $history = [];
        foreach ($checkpoints as $cp) {
            $history[] = [
                'day' => $cp->day_number,
                'summary' => (array) ($cp->state_summary ?? []),
                'recorded_hash' => (string) $cp->chain_hash,
            ];
        }

        $continuity = $this->continuityVerifier->verifyContinuity((int) $run->seed, $history);

        $valid = $continuity['valid'];
        $errorMessage = $continuity['message'];

        if ($valid && $checkpoints->isNotEmpty()) {
            $lastCp = $checkpoints->last();
            if ($run->status === 'completed' && $lastCp->chain_hash !== $run->chain_hash) {
                $valid = false;
                $errorMessage = "Hash checkpoint hari terakhir ({$lastCp->chain_hash}) tidak cocok dengan hash akhir sesi ({$run->chain_hash}).";
            }
        }

        return [
            'run_id' => $run->id,
            'status' => $run->status,
            'valid' => $valid,
            'total_checkpoints' => $checkpoints->count(),
            'chain_continuous' => $continuity['valid'],
            'error_message' => $valid ? null : $errorMessage,
        ];
    }

    /**
     * Membandingkan dua sesi simulasi secara menyeluruh.
     */
    public function auditComparison(SimRun $runA, SimRun $runB): DivergencePoint
    {
        return $this->diffAction->execute($runA, $runB);
    }
}
