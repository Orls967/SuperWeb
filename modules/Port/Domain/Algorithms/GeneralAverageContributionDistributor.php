<?php

declare(strict_types=1);

namespace Modules\Port\Domain\Algorithms;

use Modules\Shared\Domain\Algorithms\Port\GeneralAverageContributionDistributor as KernelGeneralAverageContributionDistributor;

/**
 * Port Module Wrapper for GeneralAverageContributionDistributor.
 */
final class GeneralAverageContributionDistributor
{
    private KernelGeneralAverageContributionDistributor $kernel;

    public function __construct(?KernelGeneralAverageContributionDistributor $kernel = null)
    {
        $this->kernel = $kernel ?? new KernelGeneralAverageContributionDistributor();
    }

    public function calculateAdjustment(
        float $shipSoundValueUsd,
        float $shipParticularAverageDamageUsd,
        float $cargoCommercialValueUsd,
        float $cargoParticularAverageDamageUsd,
        float $freightAtRiskUsd,
        float $freightPortExpensesSavedUsd,
        array $losses
    ): array {
        return $this->kernel->calculateAdjustment(
            $shipSoundValueUsd,
            $shipParticularAverageDamageUsd,
            $cargoCommercialValueUsd,
            $cargoParticularAverageDamageUsd,
            $freightAtRiskUsd,
            $freightPortExpensesSavedUsd,
            $losses
        );
    }
}

