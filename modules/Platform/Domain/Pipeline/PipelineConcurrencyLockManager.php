<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Pipeline;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

final class PipelineConcurrencyLockManager
{
    private const LOCK_TTL_SECONDS = 1800; // 30 minutes max lock duration

    /**
     * @return array{
     *     acquired: bool,
     *     lock_key: string,
     *     lease_token: string|null,
     *     expires_at: string|null
     * }
     */
    public function acquireEnvironmentLock(string $environment, string $pipelineExecutionId): array
    {
        $lockKey = "plt:pipeline:lock:{$environment}";
        $leaseToken = "lease-{$pipelineExecutionId}-".bin2hex(random_bytes(6));

        $acquired = Cache::add($lockKey, $leaseToken, self::LOCK_TTL_SECONDS);

        if (! $acquired) {
            return [
                'acquired' => false,
                'lock_key' => $lockKey,
                'lease_token' => null,
                'expires_at' => null,
            ];
        }

        return [
            'acquired' => true,
            'lock_key' => $lockKey,
            'lease_token' => $leaseToken,
            'expires_at' => CarbonImmutable::now()->addSeconds(self::LOCK_TTL_SECONDS)->toIso8601String(),
        ];
    }

    public function releaseEnvironmentLock(string $environment, string $leaseToken): bool
    {
        $lockKey = "plt:pipeline:lock:{$environment}";
        $currentToken = Cache::get($lockKey);

        if ($currentToken === $leaseToken) {
            Cache::forget($lockKey);

            return true;
        }

        return false;
    }

    public function isEnvironmentLocked(string $environment): bool
    {
        $lockKey = "plt:pipeline:lock:{$environment}";

        return Cache::has($lockKey);
    }
}
