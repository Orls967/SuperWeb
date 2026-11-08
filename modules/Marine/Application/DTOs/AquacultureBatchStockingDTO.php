<?php

declare(strict_types=1);

namespace Modules\Marine\Application\DTOs;

use DomainException;

/**
 * DTO pendaftaran tebar benur SPF tambak (Fase 171).
 */
class AquacultureBatchStockingDTO
{
    public function __construct(
        public readonly int $hatcheryId,
        public readonly int $pondId,
        public readonly string $speciesScientificName,
        public readonly string $commercialName,
        public readonly int $initialStockCount,
        public readonly float $pondAreaHectares,
        public readonly float $averageBodyWeightGrams,
        public readonly string $stockingDate,
        public readonly string $expectedHarvestDate,
        public readonly float $targetHarvestWeightGrams,
        public readonly string $certificationScope = 'ASC_BAP'
    ) {
        $this->validate();
    }

    protected function validate(): void
    {
        if ($this->hatcheryId <= 0 || $this->pondId <= 0) {
            throw new DomainException('ID Hatchery dan Pond harus valid.');
        }

        if ($this->initialStockCount <= 0) {
            throw new DomainException('Jumlah tebar benur harus lebih besar dari 0.');
        }

        if ($this->pondAreaHectares <= 0.0) {
            throw new DomainException('Luas area tambak harus lebih besar dari 0 hektar.');
        }

        if ($this->targetHarvestWeightGrams <= $this->averageBodyWeightGrams) {
            throw new DomainException('Target bobot panen harus lebih besar dari bobot tebar awal.');
        }
    }

    public function calculateDensityPerSquareMeter(): float
    {
        $areaM2 = $this->pondAreaHectares * 10000.0;

        return round($this->initialStockCount / $areaM2, 2);
    }

    public function calculateTargetBiomassKg(): float
    {
        // Asumsi survival rate konservatif 85%
        $expectedCount = $this->initialStockCount * 0.85;

        return round(($expectedCount * $this->targetHarvestWeightGrams) / 1000.0, 2);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            hatcheryId: (int) ($payload['hatchery_id'] ?? 0),
            pondId: (int) ($payload['pond_id'] ?? 0),
            speciesScientificName: (string) ($payload['species_scientific_name'] ?? 'Litopenaeus vannamei'),
            commercialName: (string) ($payload['commercial_name'] ?? 'Pacific Whiteleg Shrimp'),
            initialStockCount: (int) ($payload['initial_stock_count'] ?? 100000),
            pondAreaHectares: (float) ($payload['pond_area_hectares'] ?? 0.5),
            averageBodyWeightGrams: (float) ($payload['average_body_weight_grams'] ?? 0.002),
            stockingDate: (string) ($payload['stocking_date'] ?? now()->toDateString()),
            expectedHarvestDate: (string) ($payload['expected_harvest_date'] ?? now()->addDays(90)->toDateString()),
            targetHarvestWeightGrams: (float) ($payload['target_harvest_weight_grams'] ?? 25.0),
            certificationScope: (string) ($payload['certification_scope'] ?? 'ASC_BAP')
        );
    }
}
