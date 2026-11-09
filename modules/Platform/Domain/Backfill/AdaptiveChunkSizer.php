<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Backfill;

final class AdaptiveChunkSizer
{
    private const MIN_CHUNK = 20;

    private const MAX_CHUNK = 2000;

    /**
     * Calculates recommended chunk size for next batch based on execution latency and DB load.
     *
     * @param  int  $currentChunkSize  e.g. 100
     * @param  float  $lastBatchDurationMs  e.g. 45.5 ms
     * @param  float  $targetBatchDurationMs  e.g. 50.0 ms
     */
    public function adjustChunkSize(int $currentChunkSize, float $lastBatchDurationMs, float $targetBatchDurationMs = 50.0): int
    {
        if ($lastBatchDurationMs <= 0.0) {
            return $currentChunkSize;
        }

        // If batch took longer than 2x target, shrink aggressively by 50%
        if ($lastBatchDurationMs > ($targetBatchDurationMs * 2.0)) {
            $newSize = (int) floor($currentChunkSize * 0.5);

            return max(self::MIN_CHUNK, $newSize);
        }

        // If batch was very fast (< 0.5x target), expand by 25%
        if ($lastBatchDurationMs < ($targetBatchDurationMs * 0.5)) {
            $newSize = (int) ceil($currentChunkSize * 1.25);

            return min(self::MAX_CHUNK, $newSize);
        }

        return $currentChunkSize;
    }
}
