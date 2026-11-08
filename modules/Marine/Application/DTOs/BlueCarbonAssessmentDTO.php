<?php

declare(strict_types=1);

namespace Modules\Marine\Application\DTOs;

use DomainException;

/**
 * DTO survei biomassa dan stok karbon mangrove silvofishery (Fase 171).
 */
class BlueCarbonAssessmentDTO
{
    public function __construct(
        public readonly int $projectId,
        public readonly string $surveyDate,
        public readonly float $meanDbhCm,
        public readonly int $treeDensityPerHectare,
        public readonly float $abovegroundBiomassTonnesPerHa,
        public readonly float $belowgroundBiomassTonnesPerHa,
        public readonly float $soilOrganicCarbonTonnesPerHa,
        public readonly float $carbonStockTco2ePerHa,
        public readonly float $annualSequestrationRateTco2eYr,
        public readonly float $netCreditableCarbonTonnesCo2e,
        public readonly string $thirdPartyAuditorName
    ) {
        $this->validate();
    }

    protected function validate(): void
    {
        if ($this->projectId <= 0) {
            throw new DomainException('Project ID harus valid.');
        }

        if ($this->meanDbhCm <= 0.0 || $this->treeDensityPerHectare <= 0) {
            throw new DomainException('Diameter pohon dan kerapatan per hektar harus positif.');
        }
    }

    public function calculateVegetationBiomassTotal(): float
    {
        return round($this->abovegroundBiomassTonnesPerHa + $this->belowgroundBiomassTonnesPerHa, 2);
    }
}
