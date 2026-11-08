<?php

declare(strict_types=1);

namespace Modules\Port\Domain\Algorithms;

use Modules\Shared\Domain\Algorithms\Port\BunkerFuelConsumptionOptimizer as KernelBunkerFuelConsumptionOptimizer;

/**
 * Port Module Wrapper for BunkerFuelConsumptionOptimizer.
 */
final class BunkerFuelConsumptionOptimizer
{
    private KernelBunkerFuelConsumptionOptimizer $kernel;

    public function __construct(?KernelBunkerFuelConsumptionOptimizer $kernel = null)
    {
        $this->kernel = $kernel ?? new KernelBunkerFuelConsumptionOptimizer();
    }

    public function calculateDailyConsumption(
        float $displacementTons,
        float $speedKnots,
        float $designSpeedKnots,
        float $designPowerKw,
        float $sfocBaseGramsKwh = 175.0,
        float $auxiliaryLoadKw = 650.0,
        float $auxiliarySfoc = 210.0,
        int $beaufortScale = 3,
        string $fuelType = 'VLSFO'
    ): array {
        return $this->kernel->calculateDailyConsumption(
            $displacementTons,
            $speedKnots,
            $designSpeedKnots,
            $designPowerKw,
            $sfocBaseGramsKwh,
            $auxiliaryLoadKw,
            $auxiliarySfoc,
            $beaufortScale,
            $fuelType
        );
    }

    public function calculateVoyageBunker(
        float $distanceNm,
        float $speedKnots,
        float $displacementTons,
        float $designSpeedKnots,
        float $designPowerKw,
        int $seaMarginPercentage = 5,
        float $portDaysAtLoad = 2.0,
        float $portDaysAtDisch = 2.5,
        float $portDailyBoilerAndAuxMt = 3.5
    ): array {
        return $this->kernel->calculateVoyageBunker(
            $distanceNm,
            $speedKnots,
            $displacementTons,
            $designSpeedKnots,
            $designPowerKw,
            $seaMarginPercentage,
            $portDaysAtLoad,
            $portDaysAtDisch,
            $portDailyBoilerAndAuxMt
        );
    }

    public function optimizeBunkeringPort(
        array $portCandidates,
        float $bunkerRequirementMt,
        float $dailyCharterRateUsd = 18000.0,
        float $transitSpeedKnots = 13.0,
        float $dailyConsumptionMt = 28.0
    ): array {
        return $this->kernel->optimizeBunkeringPort(
            $portCandidates,
            $bunkerRequirementMt,
            $dailyCharterRateUsd,
            $transitSpeedKnots,
            $dailyConsumptionMt
        );
    }
}

