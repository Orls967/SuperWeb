<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms\Marketplace;

final readonly class SecondaryMarketTaxWaterfallInput
{
    public function __construct(
        public int $resalePriceIdr,
        public int $originalFaceValueIdr,
        public int $taxRateBps = 1000,
        public int $platformFeeRateBps = 800,
        public int $promoterRoyaltyBps = 200,
    ) {}
}
