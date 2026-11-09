<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Services;

use Closure;
use Modules\Platform\Domain\Models\DataBackfill;
use Modules\Platform\Domain\Models\DataBackfillChunk;

class BackfillChunkProcessor
{
    /**
     * Executes the next chunk idempotently.
     */
    public function processNextChunk(DataBackfill $backfill, Closure $executor): int
    {
        $chunkIndex = (int) ceil(($backfill->cursor_pointer + 1) / $backfill->chunk_size);
        $startId = $backfill->cursor_pointer + 1;
        $endId = min($backfill->total_records, $startId + $backfill->chunk_size - 1);

        $chunk = DataBackfillChunk::firstOrCreate(
            [
                'backfill_id' => $backfill->id,
                'chunk_index' => $chunkIndex,
            ],
            [
                'range_start' => $startId,
                'range_end' => $endId,
                'records_processed' => 0,
                'status' => 'processing',
                'retry_count' => 0,
            ]
        );

        if ($chunk->status === 'completed') {
            return $chunk->records_processed;
        }

        try {
            // Simulated range of records
            $records = range($startId, $endId);
            $processedCount = (int) $executor($records);

            $chunk->records_processed = $processedCount;
            $chunk->status = 'completed';
            $chunk->save();

            $backfill->cursor_pointer = $endId;
            if ($backfill->cursor_pointer >= $backfill->total_records) {
                $backfill->status = 'completed';
            } else {
                $backfill->status = 'in_progress';
            }
            $backfill->save();

            return $processedCount;
        } catch (\Throwable $e) {
            $chunk->status = 'failed';
            $chunk->retry_count += 1;
            $chunk->error_message = $e->getMessage();
            $chunk->save();

            $backfill->status = 'failed';
            $backfill->save();

            throw $e;
        }
    }
}
