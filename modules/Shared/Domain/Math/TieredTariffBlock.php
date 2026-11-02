<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Math;

use InvalidArgumentException;

/**
 * Value object representing a progressive consumption tariff tier [minUnits, maxUnits).
 */
final readonly class TieredTariffBlock
{
    /**
     * @param  int  $minUnits  Inclusive lower bound of units in this tier (>= 0)
     * @param  int|null  $maxUnits  Exclusive upper bound (null for unbounded / infinite tier)
     * @param  int  $ratePerUnit  Base price per unit in IDR integer rupiah
     */
    public function __construct(
        public int $minUnits,
        public ?int $maxUnits,
        public int $ratePerUnit,
    ) {
        if ($minUnits < 0) {
            throw new InvalidArgumentException("minUnits cannot be negative: {$minUnits}");
        }
        if ($maxUnits !== null && $maxUnits <= $minUnits) {
            throw new InvalidArgumentException("maxUnits must be strictly greater than minUnits: {$maxUnits} <= {$minUnits}");
        }
        if ($ratePerUnit < 0) {
            throw new InvalidArgumentException("ratePerUnit cannot be negative: {$ratePerUnit}");
        }
    }
}
