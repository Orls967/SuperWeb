<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms\Marketplace;

final readonly class SecondaryMarketTaxWaterfallCertificate
{
    public function __construct(
        public int $resalePriceIdr,
        public int $taxPbjtIdr,
        public int $platformFeeIdr,
        public int $promoterRoyaltyIdr,
        public int $sellerPayoutIdr,
        public string $hmacSignature,
    ) {}

    public function isValid(): bool
    {
        return SecondaryMarketTaxWaterfallVerifier::verify($this);
    }
}
