<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Simulation;

use Illuminate\Support\Facades\Cache;

/**
 * Layanan penguncian konkurensi terdistribusi untuk sesi simulasi aktif.
 */
final class SimulationConcurrencyLockService
{
    public const DEFAULT_TIMEOUT_SECONDS = 300;

    public function acquireLock(string $runId, int $timeoutSeconds = self::DEFAULT_TIMEOUT_SECONDS): bool
    {
        $lockKey = "sim:lock:run:{$runId}";

        return (bool) Cache::add($lockKey, [
            'locked_at' => now()->timestamp,
            'pid' => getmypid(),
        ], $timeoutSeconds);
    }

    public function releaseLock(string $runId): void
    {
        $lockKey = "sim:lock:run:{$runId}";
        Cache::forget($lockKey);
    }

    public function isLocked(string $runId): bool
    {
        $lockKey = "sim:lock:run:{$runId}";

        return Cache::has($lockKey);
    }
}
