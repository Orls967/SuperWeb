<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Backfill;

final class BackfillRateLimiter
{
    private int $maxTps;

    public function __construct(int $maxTps = 200)
    {
        $this->maxTps = max(10, $maxTps);
    }

    /**
     * Calculates required sleep pause in microseconds to throttle execution rate.
     */
    public function computeSleepMicroseconds(int $recordsProcessed, float $elapsedSeconds): int
    {
        if ($elapsedSeconds <= 0.0) {
            return 1000;
        }

        $currentTps = $recordsProcessed / $elapsedSeconds;
        if ($currentTps > $this->maxTps) {
            $desiredDuration = $recordsProcessed / $this->maxTps;
            $sleepNeededSeconds = $desiredDuration - $elapsedSeconds;

            return max(0, (int) round($sleepNeededSeconds * 1_000_000));
        }

        return 0;
    }
}
