<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Backfill;

use Carbon\CarbonImmutable;
use Modules\Platform\Domain\Models\DataBackfill;
use Modules\Platform\Domain\Models\DataBackfillChunk;
use RuntimeException;

final class BackfillResumptionCoordinator
{
    /**
     * @return array{
     *     resumed_chunks_count: int,
     *     dead_letter_recovered_count: int,
     *     status: string
     * }
     */
    public function resumeStalledBackfill(DataBackfill $backfill, int $staleMinutes = 30): array
    {
        if ($backfill->status !== 'running' && $backfill->status !== 'failed') {
            throw new RuntimeException("Backfill {$backfill->job_name} cannot be resumed from status {$backfill->status}");
        }

        $cutoff = CarbonImmutable::now()->subMinutes($staleMinutes);

        // Find processing chunks that haven't updated in $staleMinutes
        $staleChunks = DataBackfillChunk::query()
            ->where('data_backfill_id', $backfill->id)
            ->where('status', 'processing')
            ->where('updated_at', '<=', $cutoff)
            ->get();

        $resumedCount = 0;
        foreach ($staleChunks as $chunk) {
            $chunk->status = 'pending';
            $meta = $chunk->metadata ?? [];
            $meta['resumed_from_stale'] = true;
            $meta['resumed_at'] = CarbonImmutable::now()->toIso8601String();
            $chunk->metadata = $meta;
            $chunk->save();
            $resumedCount++;
        }

        // Retry failed chunks if under retry limit
        $failedChunks = DataBackfillChunk::query()
            ->where('data_backfill_id', $backfill->id)
            ->where('status', 'failed')
            ->where('retry_count', '<', 3)
            ->get();

        $recoveredCount = 0;
        foreach ($failedChunks as $fChunk) {
            $fChunk->status = 'pending';
            $fChunk->retry_count++;
            $meta = $fChunk->metadata ?? [];
            $meta['retry_requeued_at'] = CarbonImmutable::now()->toIso8601String();
            $fChunk->metadata = $meta;
            $fChunk->save();
            $recoveredCount++;
        }

        if ($backfill->status === 'failed' && ($resumedCount > 0 || $recoveredCount > 0)) {
            $backfill->status = 'running';
            $backfill->save();
        }

        return [
            'resumed_chunks_count' => $resumedCount,
            'dead_letter_recovered_count' => $recoveredCount,
            'status' => $backfill->status,
        ];
    }

    /**
     * @return array{
     *     completed_percent: float,
     *     is_complete: bool,
     *     pending_chunks: int,
     *     processing_chunks: int,
     *     completed_chunks: int,
     *     failed_chunks: int
     * }
     */
    public function inspectBackfillStatus(DataBackfill $backfill): array
    {
        $chunks = DataBackfillChunk::query()
            ->where('data_backfill_id', $backfill->id)
            ->get();

        $total = $chunks->count();
        if ($total === 0) {
            return [
                'completed_percent' => 100.0,
                'is_complete' => true,
                'pending_chunks' => 0,
                'processing_chunks' => 0,
                'completed_chunks' => 0,
                'failed_chunks' => 0,
            ];
        }

        $completed = $chunks->where('status', 'completed')->count();
        $pending = $chunks->where('status', 'pending')->count();
        $processing = $chunks->where('status', 'processing')->count();
        $failed = $chunks->where('status', 'failed')->count();

        $pct = round(($completed / $total) * 100, 2);

        return [
            'completed_percent' => $pct,
            'is_complete' => $completed === $total,
            'pending_chunks' => $pending,
            'processing_chunks' => $processing,
            'completed_chunks' => $completed,
            'failed_chunks' => $failed,
        ];
    }
}
