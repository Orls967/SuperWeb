<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Backfill;

use Modules\Platform\Domain\Models\DataBackfill;
use Modules\Platform\Domain\Models\DataBackfillChunk;

final class BackfillCheckpointRepository
{
    /**
     * Finds the highest completed chunk ID to resume safely after unexpected process crashes.
     */
    public function getLatestCheckpoint(DataBackfill $backfill): int
    {
        $lastCompleted = DataBackfillChunk::where('backfill_id', $backfill->id)
            ->where('status', 'completed')
            ->max('end_id');

        return $lastCompleted !== null ? (int) $lastCompleted : 0;
    }

    /**
     * Resets stuck processing chunks that timed out.
     */
    public function reclaimStuckChunks(DataBackfill $backfill, int $timeoutMinutes = 15): int
    {
        $cutoff = now()->subMinutes($timeoutMinutes);

        return DataBackfillChunk::where('backfill_id', $backfill->id)
            ->where('status', 'processing')
            ->where('updated_at', '<', $cutoff)
            ->update([
                'status' => 'pending',
                'retry_count' => \DB::raw('retry_count + 1'),
            ]);
    }
}
