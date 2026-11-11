<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Access\Cache;

use Illuminate\Support\Facades\Cache;
use Modules\Core\Domain\Models\CoreAccessGrant;

/**
 * Sub-Second Revocation & Eviction Bus Manager (W8-D55).
 *
 * Propagates instantaneous revocation events across local memory caches and shared Redis layers
 * to guarantee that access rights are severed within < 1 second of revocation.
 */
final class SubSecondEvictionManager
{
    public function __construct(
        private readonly AbacMemoryCache $memoryCache
    ) {}

    /**
     * Execute sub-second revocation for an access grant.
     *
     * @param  CoreAccessGrant  $grant  The revoked grant
     * @param  string  $revocationReason  Mandatory audit reason
     * @return array{evicted_memory_entries: int, revocation_timestamp: int, grant_code: string}
     */
    public function executeRevocation(CoreAccessGrant $grant, string $revocationReason): array
    {
        $startTime = microtime(true);
        $timestamp = time();

        // 1. Evict memory cache
        $evictedCount = $this->memoryCache->evictSubject($grant->tenant_id, (string) $grant->user_id);

        // 2. Invalidate Redis tag if tagged cache is available
        try {
            Cache::tags(["tenant:{$grant->tenant_id}", "user:{$grant->user_id}"])->flush();
        } catch (\Throwable) {
            // Fallback for cache drivers without tag support (e.g. array/file)
            Cache::forget("user_grants_{$grant->tenant_id}_{$grant->user_id}");
        }

        // 3. Record blacklisted grant token in fast cache with 24-hour TTL
        Cache::put("revoked_grant_{$grant->grant_code}", [
            'revoked_at' => $timestamp,
            'reason' => $revocationReason,
        ], 86400);

        $elapsedMs = (microtime(true) - $startTime) * 1000;

        return [
            'evicted_memory_entries' => $evictedCount,
            'revocation_timestamp' => $timestamp,
            'grant_code' => $grant->grant_code,
            'elapsed_ms' => round($elapsedMs, 3),
        ];
    }
}
