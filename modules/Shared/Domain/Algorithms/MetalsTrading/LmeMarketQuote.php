<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\MetalsTrading;

final class LmeMarketQuote
{
    public function __construct(
        public readonly string $metalSymbol,
        public readonly int $cashPriceUsdCentsPerTon,
        public readonly int $threeMonthPriceUsdCentsPerTon,
        public readonly int $exchangeRateIdrPerUsd,
        public readonly float $annualizedInterestRatePct = 5.25,
        public readonly float $historicalVolatilityPct = 24.50,
        public readonly \DateTimeImmutable $quoteTimestamp = new \DateTimeImmutable,
    ) {
        if ($this->cashPriceUsdCentsPerTon <= 0) {
            throw new \InvalidArgumentException('LME Cash price must be positive.');
        }
        if ($this->threeMonthPriceUsdCentsPerTon <= 0) {
            throw new \InvalidArgumentException('LME 3-month price must be positive.');
        }
        if ($this->exchangeRateIdrPerUsd <= 0) {
            throw new \InvalidArgumentException('USD/IDR exchange rate must be positive.');
        }
    }

    public function spreadContangoBackwardationUsdCents(): int
    {
        return $this->threeMonthPriceUsdCentsPerTon - $this->cashPriceUsdCentsPerTon;
    }

    public function isContango(): bool
    {
        return $this->spreadContangoBackwardationUsdCents() >= 0;
    }
}
