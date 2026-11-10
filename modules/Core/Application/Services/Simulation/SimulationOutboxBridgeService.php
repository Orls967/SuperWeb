<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Simulation;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Domain\Models\SimCheckpoint;
use Modules\Core\Domain\Models\SimRun;

/**
 * Jembatan penerbitan event outbox deterministik untuk siklus hidup simulasi.
 * Mencatat event ke tabel `core_outbox` untuk konsumsi event-spine dan audit trail.
 */
final class SimulationOutboxBridgeService
{
    public function publishRunStateChanged(SimRun $run, string $fromStatus, string $toStatus, string $reason): void
    {
        $payload = [
            'event_id' => (string) Str::uuid(),
            'event_type' => 'core.simulation.run_state_changed.v1',
            'run_id' => $run->id,
            'run_name' => $run->name,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'current_day' => $run->current_day,
            'chain_hash' => $run->chain_hash,
            'reason' => $reason,
            'occurred_at' => now()->toIso8601String(),
        ];

        $this->insertOutbox('core.simulation.run_state_changed.v1', $run->id, $payload);
    }

    public function publishCheckpointWritten(SimRun $run, SimCheckpoint $checkpoint): void
    {
        $payload = [
            'event_id' => (string) Str::uuid(),
            'event_type' => 'core.simulation.checkpoint_written.v1',
            'checkpoint_id' => $checkpoint->id,
            'run_id' => $run->id,
            'day_number' => $checkpoint->day_number,
            'chain_hash' => $checkpoint->chain_hash,
            'handler_hashes' => $checkpoint->handler_hashes,
            'occurred_at' => now()->toIso8601String(),
        ];

        $this->insertOutbox('core.simulation.checkpoint_written.v1', $run->id, $payload);
    }

    public function publishDayCompleted(SimRun $run, int $dayNumber, string $chainHash, array $metrics = []): void
    {
        $payload = [
            'event_id' => (string) Str::uuid(),
            'event_type' => 'core.simulation.day_completed.v1',
            'run_id' => $run->id,
            'day_number' => $dayNumber,
            'chain_hash' => $chainHash,
            'metrics' => $metrics,
            'occurred_at' => now()->toIso8601String(),
        ];

        $this->insertOutbox('core.simulation.day_completed.v1', $run->id, $payload);
    }

    public function publishDivergenceDetected(string $runAId, string $runBId, int $day, string $reason): void
    {
        $payload = [
            'event_id' => (string) Str::uuid(),
            'event_type' => 'core.simulation.divergence_detected.v1',
            'run_a_id' => $runAId,
            'run_b_id' => $runBId,
            'divergence_day' => $day,
            'reason' => $reason,
            'occurred_at' => now()->toIso8601String(),
        ];

        $this->insertOutbox('core.simulation.divergence_detected.v1', "{$runAId}:{$runBId}", $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function insertOutbox(string $eventType, string $aggregateId, array $payload): void
    {
        if (DB::getSchemaBuilder()->hasTable('core_outbox')) {
            DB::table('core_outbox')->insert([
                'id' => (string) Str::uuid(),
                'event_type' => $eventType,
                'aggregate_type' => 'sim_run',
                'aggregate_id' => $aggregateId,
                'payload' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
