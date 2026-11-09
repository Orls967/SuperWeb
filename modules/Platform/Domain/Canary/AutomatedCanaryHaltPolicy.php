<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Canary;

final class AutomatedCanaryHaltPolicy
{
    /**
     * Assesses whether instant emergency halt must be applied without waiting for full sample window.
     *
     * @param  int  $consecutive5xxErrors  e.g. 15 errors in a row
     * @param  float  $crashLoopPodPercentage  e.g. 50.0% of pods crashing
     */
    public function shouldInstantlyHalt(int $consecutive5xxErrors, float $crashLoopPodPercentage): bool
    {
        if ($consecutive5xxErrors >= 10) {
            return true;
        }

        if ($crashLoopPodPercentage >= 25.0) {
            return true;
        }

        return false;
    }
}
