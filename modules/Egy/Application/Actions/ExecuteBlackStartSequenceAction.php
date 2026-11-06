<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Actions;

use Modules\Egy\Domain\Models\BlackStartPlan;
use Modules\Egy\Domain\Models\Microgrid;
use Modules\Egy\Domain\StateMachines\MicrogridOperatingModeStateMachine;
use Modules\Shared\Domain\Algorithms\Utility\BlackStartSynchronizationSequencer;

/**
 * Aksi Penjadwalan dan Eksekusi Urutan Pemulihan Black-Start Microgrid (Topological Sort E14).
 */
final class ExecuteBlackStartSequenceAction
{
    public function __construct(
        private readonly BlackStartSynchronizationSequencer $sequencer,
        private readonly MicrogridOperatingModeStateMachine $stateMachine,
    ) {}

    /**
     * @param list<array{
     *     code: string,
     *     name: string,
     *     dependencies: list<string>,
     *     step_duration_seconds: int,
     *     inrush_current_amp: int
     * }> $components
     */
    public function execute(int $microgridId, array $components, string $planCode): BlackStartPlan
    {
        $microgrid = Microgrid::findOrFail($microgridId);

        $existing = BlackStartPlan::where('plan_code', $planCode)->first();
        if ($existing !== null) {
            return $existing;
        }

        // Transisi status microgrid menuju pemulihan
        if ($microgrid->operating_mode === MicrogridOperatingModeStateMachine::MODE_BLACKOUT) {
            $microgrid->operating_mode = $this->stateMachine->transition(
                $microgrid->operating_mode,
                MicrogridOperatingModeStateMachine::MODE_RESTORING
            );
            $microgrid->save();
        }

        $result = $this->sequencer->planSequence($microgrid->microgrid_code, $components);

        // Simulasi keberhasilan eksekusi urutan langkah
        $executionLog = [];
        foreach ($result->sequence as $step) {
            $executionLog[] = [
                'step' => $step['step'],
                'component_code' => $step['component_code'],
                'status' => 'energized_ok',
                'timestamp' => now()->toISOString(),
            ];
        }

        // Setelah seluruh langkah selesai tanpa trip, microgrid stabil di mode pulau (islanded)
        if ($microgrid->operating_mode === MicrogridOperatingModeStateMachine::MODE_RESTORING) {
            $microgrid->operating_mode = $this->stateMachine->transition(
                $microgrid->operating_mode,
                MicrogridOperatingModeStateMachine::MODE_ISLANDED
            );
            $microgrid->save();
        }

        return BlackStartPlan::create([
            'plan_code' => $planCode,
            'microgrid_id' => $microgrid->id,
            'status' => 'completed',
            'total_components_count' => $result->totalComponentsCount,
            'total_estimated_seconds' => $result->totalEstimatedSeconds,
            'step_sequence_json' => $result->sequence,
            'execution_log_json' => $executionLog,
            'certificate_hash' => $result->certificateHash,
        ]);
    }
}
