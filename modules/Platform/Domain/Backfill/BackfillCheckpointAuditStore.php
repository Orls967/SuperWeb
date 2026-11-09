<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Backfill;

use Carbon\CarbonImmutable;
use Modules\Platform\Domain\Models\DataBackfillChunk;

final class BackfillCheckpointAuditStore
{
    /**
     * @param  array<string, mixed>  $diagnosticPayload
     */
    public function recordCheckpoint(
        DataBackfillChunk $chunk,
        int $processedInChunk,
        float $durationSeconds,
        array $diagnosticPayload = []
    ): void {
        $meta = $chunk->metadata ?? [];
        $checkpoints = $meta['checkpoints'] ?? [];

        $checkpoints[] = [
            'checkpoint_id' => 'chk-'.bin2hex(random_bytes(4)),
            'processed_count' => $processedInChunk,
            'duration_seconds' => round($durationSeconds, 3),
            'throughput_per_sec' => $durationSeconds > 0 ? round($processedInChunk / $durationSeconds, 2) : 0,
            'recorded_at' => CarbonImmutable::now()->toIso8601String(),
            'diagnostics' => $diagnosticPayload,
        ];

        $meta['checkpoints'] = $checkpoints;
        $meta['last_checkpoint_at'] = CarbonImmutable::now()->toIso8601String();
        $chunk->metadata = $meta;
        $chunk->save();
    }

    /**
     * @return array{
     *     total_checkpoints: int,
     *     mean_throughput_rows_per_sec: float,
     *     max_chunk_duration_seconds: float
     * }
     */
    public function aggregateChunkPerformance(DataBackfillChunk $chunk): array
    {
        $meta = $chunk->metadata ?? [];
        $checkpoints = $meta['checkpoints'] ?? [];

        if (empty($checkpoints)) {
            return [
                'total_checkpoints' => 0,
                'mean_throughput_rows_per_sec' => 0.0,
                'max_chunk_duration_seconds' => 0.0,
            ];
        }

        $throughputs = [];
        $durations = [];

        foreach ($checkpoints as $cp) {
            $throughputs[] = $cp['throughput_per_sec'] ?? 0;
            $durations[] = $cp['duration_seconds'] ?? 0;
        }

        return [
            'total_checkpoints' => count($checkpoints),
            'mean_throughput_rows_per_sec' => round(array_sum($throughputs) / count($throughputs), 2),
            'max_chunk_duration_seconds' => round(max($durations), 3),
        ];
    }
}
