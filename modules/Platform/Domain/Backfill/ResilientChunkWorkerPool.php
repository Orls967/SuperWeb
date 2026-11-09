<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Backfill;

use Closure;
use Modules\Platform\Domain\Models\DataBackfill;
use Modules\Platform\Domain\Models\DataBackfillChunk;

final class ResilientChunkWorkerPool
{
    /**
     * Executes chunk batch with exponential backoff on transient deadlock or lock wait timeout.
     */
    public function executeWithRetry(DataBackfill $backfill, DataBackfillChunk $chunk, Closure $batchJob, int $maxAttempts = 3): int
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxAttempts) {
            $attempt++;
            try {
                $processed = (int) $batchJob($chunk->range_start, $chunk->range_end);

                $chunk->records_processed = $processed;
                $chunk->status = 'completed';
                $chunk->save();

                return $processed;
            } catch (\Throwable $e) {
                $lastException = $e;
                if ($attempt < $maxAttempts) {
                    $sleepMs = (int) pow(2, $attempt) * 50;
                    usleep($sleepMs * 1000);
                }
            }
        }

        $chunk->status = 'failed';
        $chunk->retry_count = $attempt;
        $chunk->error_message = $lastException?->getMessage();
        $chunk->save();

        throw $lastException ?? new \RuntimeException("Chunk #{$chunk->chunk_index} gagal diproses setelah {$maxAttempts} percobaan.");
    }
}
