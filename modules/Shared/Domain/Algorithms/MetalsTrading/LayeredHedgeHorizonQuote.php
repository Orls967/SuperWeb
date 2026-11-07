<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\MetalsTrading;

use InvalidArgumentException;

final class LayeredHedgeHorizonQuote
{
    /**
     * @param  string  $monthTenor  e.g. "2026-12", "2027-01"
     * @param  float  $plannedProductionTons  Planned metal production output for the month
     * @param  float  $existingHedgedTons  Volume already hedged under prior derivatives
     * @param  float  $targetHedgeRatio  Minimum target hedge ratio (e.g. 0.50 for 50%)
     * @param  float  $lmeForwardPriceUsdPerTon  Market forward price quote in USD per ton
     * @param  float  $usdIdrExchangeRate  Exchange rate assumption
     */
    public function __construct(
        public readonly string $monthTenor,
        public readonly float $plannedProductionTons,
        public readonly float $existingHedgedTons,
        public readonly float $targetHedgeRatio,
        public readonly int $lmeForwardPriceUsdPerTon,
        public readonly int $exchangeRateIdrPerUsd,
    ) {
        if ($this->plannedProductionTons <= 0.0) {
            throw new InvalidArgumentException("Planned production for {$this->monthTenor} must be positive.");
        }
        if ($this->existingHedgedTons < 0.0) {
            throw new InvalidArgumentException('Existing hedged tons cannot be negative.');
        }
        if ($this->targetHedgeRatio < 0.0 || $this->targetHedgeRatio > 1.0) {
            throw new InvalidArgumentException('Target hedge ratio must be between 0.0 and 1.0.');
        }
        if ($this->lmeForwardPriceUsdPerTon <= 0) {
            throw new InvalidArgumentException('LME forward price must be positive.');
        }
        if ($this->exchangeRateIdrPerUsd <= 0) {
            throw new InvalidArgumentException('USD/IDR exchange rate must be positive.');
        }
    }
}
