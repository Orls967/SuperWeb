<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\DTOs;

use Brick\Math\BigDecimal;

class ChargeableWeightResult
{
    public function __construct(
        public readonly BigDecimal $actualWeightKg,
        public readonly BigDecimal $volumetricWeightKg,
        public readonly BigDecimal $chargeableWeightKg,
        public readonly int $chargeableWeightGrams,
        public readonly BigDecimal $volumeCm3,
        public readonly BigDecimal $volumeCbm,
        public readonly ?BigDecimal $revenueTons = null,
        public readonly bool $isUnitBased = false
    ) {}
}
