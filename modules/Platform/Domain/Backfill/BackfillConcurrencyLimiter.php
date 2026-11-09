<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Backfill;

use Illuminate\Support\Facades\Cache;

final class BackfillConcurrencyLimiter
{
    private const MAX_CONCURRENT_CHUNKS = 5;

    /**
     * @return array{
     *     slot_acquired: bool,
     *     active_slots_count: int,
     *     max_slots: int
     * }
     */
    public function acquireSlot(string $backfillJobId, string $chunkId): array
    {
        $poolKey = "plt:backfill:slots:{$backfillJobId}";
        $active = Cache::get($poolKey, []);

        if (! is_array($active)) {
            $active = [];
        }

        // Clean expired slots older than 10 minutes
        $now = time();
        $filtered = array_filter($active, fn ($t) => ($now - $t) < 600);

        if (count($filtered) >= self::MAX_CONCURRENT_CHUNKS) {
            return [
                'slot_acquired' => false,
                'active_slots_count' => count($filtered),
                'max_slots' => self::MAX_CONCURRENT_CHUNKS,
            ];
        }

        $filtered[$chunkId] = $now;
        Cache::put($poolKey, $filtered, 600);

        return [
            'slot_acquired' => true,
            'active_slots_count' => count($filtered),
            'max_slots' => self::MAX_CONCURRENT_CHUNKS,
        ];
    }

    public function releaseSlot(string $backfillJobId, string $chunkId): void
    {
        $poolKey = "plt:backfill:slots:{$backfillJobId}";
        $active = Cache::get($poolKey, []);

        if (is_array($active) && isset($active[$chunkId])) {
            unset($active[$chunkId]);
            Cache::put($poolKey, $active, 600);
        }
    }
}
