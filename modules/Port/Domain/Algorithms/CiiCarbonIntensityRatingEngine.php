<?php

declare(strict_types=1);

namespace Modules\Port\Domain\Algorithms;

use Modules\Shared\Domain\Algorithms\Port\CiiCarbonIntensityRatingEngine as KernelCiiCarbonIntensityRatingEngine;

/**
 * Port Module Wrapper for CiiCarbonIntensityRatingEngine.
 */
final class CiiCarbonIntensityRatingEngine
{
    private KernelCiiCarbonIntensityRatingEngine $kernel;

    public function __construct(?KernelCiiCarbonIntensityRatingEngine $kernel = null)
    {
        $this->kernel = $kernel ?? new KernelCiiCarbonIntensityRatingEngine();
    }

    public function evaluateCii(
        string $shipType,
        float $capacityDwt,
        float $distanceTraveledNm,
        array $fuelConsumedMt,
        int $year = 2026
    ): array {
        return $this->kernel->evaluateCii(
            $shipType,
            $capacityDwt,
            $distanceTraveledNm,
            $fuelConsumedMt,
            $year
        );
    }
}

