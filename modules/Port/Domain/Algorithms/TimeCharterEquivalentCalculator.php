<?php

declare(strict_types=1);

namespace Modules\Port\Domain\Algorithms;

use Modules\Shared\Domain\Algorithms\Port\TimeCharterEquivalentCalculator as KernelTimeCharterEquivalentCalculator;

/**
 * Port Module Wrapper for TimeCharterEquivalentCalculator.
 */
final class TimeCharterEquivalentCalculator
{
    private KernelTimeCharterEquivalentCalculator $kernel;

    public function __construct(?KernelTimeCharterEquivalentCalculator $kernel = null)
    {
        $this->kernel = $kernel ?? new KernelTimeCharterEquivalentCalculator();
    }

    public function calculateTce(
        float $cargoQuantityMt,
        float $freightRateUsdPerMt,
        float $seaSteamingDays,
        float $portDaysLoad,
        float $portDaysDisch,
        float $bunkerSeaCostUsd,
        float $bunkerPortCostUsd,
        float $portDisbursementsUsd,
        float $canalDuesUsd = 0.0,
        float $ballastBonusUsd = 0.0,
        float $demurrageEarnedUsd = 0.0,
        float $despatchPaidUsd = 0.0,
        float $otherVoyageExpensesUsd = 0.0,
        float $dailyOpexTargetUsd = 6500.0
    ): array {
        return $this->kernel->calculateTce(
            $cargoQuantityMt,
            $freightRateUsdPerMt,
            $seaSteamingDays,
            $portDaysLoad,
            $portDaysDisch,
            $bunkerSeaCostUsd,
            $bunkerPortCostUsd,
            $portDisbursementsUsd,
            $canalDuesUsd,
            $ballastBonusUsd,
            $demurrageEarnedUsd,
            $despatchPaidUsd,
            $otherVoyageExpensesUsd,
            $dailyOpexTargetUsd
        );
    }
}

