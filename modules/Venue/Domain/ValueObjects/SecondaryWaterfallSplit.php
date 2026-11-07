<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\ValueObjects;

final class SecondaryWaterfallSplit
{
    public function __construct(
        public readonly int $grossResalePriceIdr,
        public readonly int $pbjtTaxIdr,
        public readonly int $platformFeeIdr,
        public readonly int $promoterRoyaltyIdr,
        public readonly int $netSellerPayoutIdr
    ) {
        if ($this->grossResalePriceIdr <= 0) {
            throw new \InvalidArgumentException('grossResalePriceIdr harus positif.');
        }

        $sum = $this->pbjtTaxIdr + $this->platformFeeIdr + $this->promoterRoyaltyIdr + $this->netSellerPayoutIdr;
        if ($sum !== $this->grossResalePriceIdr) {
            throw new \DomainException("Inkonsistensi konservasi moneter tertutup: {$sum} != {$this->grossResalePriceIdr}");
        }
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'gross_resale_price_idr' => $this->grossResalePriceIdr,
            'pbjt_tax_idr' => $this->pbjtTaxIdr,
            'platform_fee_idr' => $this->platformFeeIdr,
            'promoter_royalty_idr' => $this->promoterRoyaltyIdr,
            'net_seller_payout_idr' => $this->netSellerPayoutIdr,
        ];
    }
}
