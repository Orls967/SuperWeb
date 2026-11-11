<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Access\Cache;

use Modules\Core\Domain\Access\Values\AccessDecision;

/**
 * High-performance In-Memory & Tagged Cache for ABAC Policy Decisions (W8-D53, W8-D55).
 *
 * Implements sub-second decision caching and instant tenant-wide or subject-wide eviction.
 */
final class AbacMemoryCache
{
    /**
     * @var array<string, array{decision: AccessDecision, expires_at: int}>
     */
    private array $localCache = [];

    /**
     * @param  int  $defaultTtlSeconds  Default time-to-live for decision results (e.g. 60 seconds)
     */
    public function __construct(
        private readonly int $defaultTtlSeconds = 60
    ) {}

    /**
     * Build deterministic cache key.
     */
    public function buildCacheKey(
        string $tenantId,
        string $subjectId,
        string $resourceType,
        string $resourceId,
        string $actionCode
    ): string {
        return "abac:{$tenantId}:{$subjectId}:{$resourceType}:{$resourceId}:{$actionCode}";
    }

    /**
     * Fetch decision from cache if present and valid.
     */
    public function get(string $key): ?AccessDecision
    {
        if (! isset($this->localCache[$key])) {
            return null;
        }

        $entry = $this->localCache[$key];
        if (time() >= $entry['expires_at']) {
            unset($this->localCache[$key]);

            return null;
        }

        return $entry['decision'];
    }

    /**
     * Store decision in cache.
     */
    public function put(string $key, AccessDecision $decision, ?int $ttl = null): void
    {
        $duration = $ttl ?? $this->defaultTtlSeconds;
        $this->localCache[$key] = [
            'decision' => $decision,
            'expires_at' => time() + $duration,
        ];
    }

    /**
     * Evict all entries for a specific subject (used upon grant revocation).
     */
    public function evictSubject(string $tenantId, string $subjectId): int
    {
        $prefix = "abac:{$tenantId}:{$subjectId}:";
        $evicted = 0;

        foreach (array_keys($this->localCache) as $key) {
            if (str_starts_with($key, $prefix)) {
                unset($this->localCache[$key]);
                $evicted++;
            }
        }

        return $evicted;
    }

    /**
     * Evict all entries for a tenant (used upon policy update).
     */
    public function evictTenant(string $tenantId): int
    {
        $prefix = "abac:{$tenantId}:";
        $evicted = 0;

        foreach (array_keys($this->localCache) as $key) {
            if (str_starts_with($key, $prefix)) {
                unset($this->localCache[$key]);
                $evicted++;
            }
        }

        return $evicted;
    }

    /**
     * Clear entire local memory cache.
     */
    public function flush(): void
    {
        $this->localCache = [];
    }

    /**
     * Return current item count.
     */
    public function count(): int
    {
        return count($this->localCache);
    }
}
