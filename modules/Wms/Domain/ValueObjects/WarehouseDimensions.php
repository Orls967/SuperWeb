<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Dimensi fisik lorong & rak gudang dalam satuan milimeter (integer murni).
 */
final readonly class WarehouseDimensions
{
    public function __construct(
        public int $aisleCount,
        public int $aisleLengthMm,
        public int $aisleWidthMm,
        public int $rackDepthMm,
        public int $maxLevelHeightMm,
    ) {
        if ($aisleCount <= 0 || $aisleLengthMm <= 0 || $aisleWidthMm <= 0 || $rackDepthMm <= 0 || $maxLevelHeightMm <= 0) {
            throw new InvalidArgumentException('Seluruh dimensi gudang harus lebih dari 0.');
        }
    }

    public function totalFloorAreaMm2(): int
    {
        $bayWidth = $this->aisleWidthMm + (2 * $this->rackDepthMm);

        return $this->aisleCount * $bayWidth * $this->aisleLengthMm;
    }

    public function aisleLengthMetersRounded(): int
    {
        return intdiv($this->aisleLengthMm + 500, 1000);
    }

    public function aisleWidthMetersRounded(): int
    {
        return intdiv($this->aisleWidthMm + 500, 1000);
    }
}
