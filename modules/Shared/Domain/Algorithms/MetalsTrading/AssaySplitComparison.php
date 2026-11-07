<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\MetalsTrading;

use InvalidArgumentException;

final class AssaySplitComparison
{
    /**
     * @param  float  $lotNetWeightTons  Net certified dry weight of the metal lot
     * @param  float  $sellerNiGradePct  Seller's lab certified Ni grade %
     * @param  float  $buyerNiGradePct  Buyer's receiving lab Ni grade %
     * @param  float  $splittingLimitPct  Contractual tolerance threshold (e.g. 0.15% Ni)
     * @param  float|null  $umpireNiGradePct  Independent referee lab assay if dispute triggered
     * @param  int  $lmeOfficialPriceUsdPerTon  LME settlement quote in USD per ton
     * @param  int  $provisionalPaymentRateBps  e.g. 8500 bps = 85.0% provisional drawing
     * @param  float  $usdIdrRate  Foreign exchange conversion rate
     * @param  int  $umpireServiceFeeIdr  Cost of independent umpire referee in IDR
     */
    public function __construct(
        public readonly float $lotNetWeightTons,
        public readonly float $sellerNiGradePct,
        public readonly float $buyerNiGradePct,
        public readonly float $splittingLimitPct,
        public readonly ?float $umpireNiGradePct,
        public readonly int $lmeOfficialPriceUsdPerTon,
        public readonly int $provisionalPaymentRateBps,
        public readonly int $exchangeRateIdrPerUsd,
        public readonly int $umpireServiceFeeIdr = 0,
    ) {
        if ($this->lotNetWeightTons <= 0.0) {
            throw new InvalidArgumentException('Lot net weight must be positive.');
        }
        if ($this->sellerNiGradePct <= 0.0 || $this->buyerNiGradePct <= 0.0) {
            throw new InvalidArgumentException('Assay grades must be positive.');
        }
        if ($this->splittingLimitPct <= 0.0) {
            throw new InvalidArgumentException('Splitting limit must be positive.');
        }
        if ($this->lmeOfficialPriceUsdPerTon <= 0) {
            throw new InvalidArgumentException('LME official price must be positive.');
        }
        if ($this->provisionalPaymentRateBps <= 0 || $this->provisionalPaymentRateBps > 10000) {
            throw new InvalidArgumentException('Provisional rate must be between 1 and 10000 bps.');
        }
        if ($this->exchangeRateIdrPerUsd <= 0) {
            throw new InvalidArgumentException('USD/IDR rate must be positive.');
        }
    }
}
