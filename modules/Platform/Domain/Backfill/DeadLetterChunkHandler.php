<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Backfill;

use Modules\Platform\Domain\Models\DataBackfill;
use Modules\Platform\Domain\Models\DataBackfillChunk;

final class DeadLetterChunkHandler
{
    /**
     * Isolates permanently failing chunks into dead-letter state so remainder of table can complete.
     *
     * @return list<int> isolated chunk IDs
     */
    public function quarantineDeadChunks(DataBackfill $backfill, int $maxRetries = 5): array
    {
        $deadChunks = DataBackfillChunk::where('backfill_id', $backfill->id)
            ->where('retry_count', '>=', $maxRetries)
            ->where('status', 'failed')
            ->get();

        $isolatedIds = [];
        foreach ($deadChunks as $chunk) {
            $chunk->status = 'quarantined';
            $chunk->error_message = "[DEAD_LETTER] Dikuarantina setelah {$chunk->retry_count} kegagalan: {$chunk->error_message}";
            $chunk->save();
            $isolatedIds[] = $chunk->id;
        }

        return $isolatedIds;
    }
}
