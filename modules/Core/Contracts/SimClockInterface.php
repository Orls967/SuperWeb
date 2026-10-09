<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Carbon\CarbonInterface;
use Modules\Core\Domain\Models\SimRun;

interface SimClockInterface
{
    /**
     * Get the current virtual timestamp.
     * Falls back to Carbon::now() if simulation is not running.
     */
    public function now(): CarbonInterface;

    /**
     * Check if a simulation run is active.
     */
    public function isSimulating(): bool;

    /**
     * Get or initialize a simulation run by name.
     */
    public function initRun(string $name, CarbonInterface $startTime, int $seed = 42): SimRun;

    /**
     * Advance virtual time by a given number of seconds deterministically.
     */
    public function advanceSeconds(int $seconds): CarbonInterface;

    /**
     * Advance virtual time by days.
     */
    public function advanceDays(int $days): CarbonInterface;

    /**
     * Reset/stop simulation clock and return to wall clock.
     */
    public function reset(): void;

    /**
     * Get active SimRun instance if any.
     */
    public function activeRun(): ?SimRun;
}
