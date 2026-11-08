<?php

declare(strict_types=1);

namespace Modules\Port\Domain\Algorithms;

use Carbon\CarbonImmutable;
use Modules\Shared\Domain\Algorithms\Port\LaytimeDemurrageCalculator as KernelLaytimeDemurrageCalculator;

/**
 * Port Module Wrapper for LaytimeDemurrageCalculator.
 */
final class LaytimeDemurrageCalculator
{
    private KernelLaytimeDemurrageCalculator $kernel;

    public function __construct(?KernelLaytimeDemurrageCalculator $kernel = null)
    {
        $this->kernel = $kernel ?? new KernelLaytimeDemurrageCalculator();
    }

    public function calculateStatement(
        CarbonImmutable $norTenderedAt,
        CarbonImmutable $workCommencedAt,
        CarbonImmutable $workCompletedAt,
        float $allowedLaytimeHours,
        float $dailyDemurrageRateUsd,
        ?float $dailyDespatchRateUsd = null,
        string $terms = 'SHINC',
        int $turnTimeHours = 6,
        array $interruptions = []
    ): array {
        return $this->kernel->calculateStatement(
            $norTenderedAt,
            $workCommencedAt,
            $workCompletedAt,
            $allowedLaytimeHours,
            $dailyDemurrageRateUsd,
            $dailyDespatchRateUsd,
            $terms,
            $turnTimeHours,
            $interruptions
        );
    }

    public function calculateReversibleLaytime(
        array $loadingStatement,
        array $dischargingStatement,
        float $dailyDemurrageRateUsd,
        float $dailyDespatchRateUsd
    ): array {
        return $this->kernel->calculateReversibleLaytime(
            $loadingStatement,
            $dischargingStatement,
            $dailyDemurrageRateUsd,
            $dailyDespatchRateUsd
        );
    }
}

