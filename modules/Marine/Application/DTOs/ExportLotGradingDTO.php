<?php

declare(strict_types=1);

namespace Modules\Marine\Application\DTOs;

use DomainException;

/**
 * DTO klasifikasi mutu dan verifikasi lot produk olahan seafood (Fase 171).
 */
class ExportLotGradingDTO
{
    public function __construct(
        public readonly int $harvestOperationId,
        public readonly string $productForm,
        public readonly string $commercialGrade,
        public readonly string $sizeClassification,
        public readonly float $netLotWeightKg,
        public readonly int $packCountCartons,
        public readonly float $defectsOrganolepticPct,
        public readonly int $estimatedExportValueMinorCents,
        public readonly string $freezeMethod = 'INDIVIDUALLY_QUICK_FROZEN',
        public readonly string $storageRoomCode = 'FREEZER_ROOM_B3'
    ) {
        $this->validate();
    }

    protected function validate(): void
    {
        if ($this->harvestOperationId <= 0) {
            throw new DomainException('Harvest Operation ID harus valid.');
        }

        if ($this->netLotWeightKg <= 0.0 || $this->packCountCartons <= 0) {
            throw new DomainException('Bobot bersih dan jumlah karton harus positif.');
        }

        if ($this->estimatedExportValueMinorCents <= 0) {
            throw new DomainException('Estimasi nilai ekspor harus lebih besar dari 0 minor cents.');
        }
    }

    public function calculateAverageCartonNetWeightKg(): float
    {
        return round($this->netLotWeightKg / $this->packCountCartons, 3);
    }

    public function isTopGradeEligible(): bool
    {
        return $this->commercialGrade === 'GRADE_A1' && $this->defectsOrganolepticPct <= 0.50;
    }
}
