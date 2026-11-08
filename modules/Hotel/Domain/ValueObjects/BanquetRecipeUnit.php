<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\ValueObjects;

use InvalidArgumentException;

final class BanquetRecipeUnit
{
    public const ALLOWED_UNITS = ['kg', 'gram', 'liter', 'ml', 'portion', 'piece', 'bottle', 'can', 'crate'];

    public function __construct(
        public readonly string $unitName,
        public readonly float $standardYieldFactor = 1.0
    ) {
        if (! in_array(strtolower($this->unitName), self::ALLOWED_UNITS, true)) {
            throw new InvalidArgumentException("Invalid recipe unit: {$this->unitName}");
        }
        if ($this->standardYieldFactor <= 0.0) {
            throw new InvalidArgumentException('Yield factor must be greater than zero');
        }
    }

    public function convertToStandardBatch(float $rawQuantity): float
    {
        return round($rawQuantity / $this->standardYieldFactor, 4);
    }
}
