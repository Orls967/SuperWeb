<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms\Marketplace;

final readonly class SecondaryMarketTaxWaterfallResult
{
    public function __construct(
        public int $taxPbjtIdr,
        public int $platformFeeIdr,
        public int $promoterRoyaltyIdr,
        public int $sellerPayoutIdr,
        public ?SecondaryMarketTaxWaterfallCertificate $certificate = null,
    ) {}
}
