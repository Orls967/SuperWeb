<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\ValueObjects;

final class ResaleCommissionBreakdown
{
    public const PBJT_BPS = 1000;      // 10.00%

    public const PLATFORM_BPS = 800;   // 8.00%

    public const PROMOTER_BPS = 200;   // 2.00%

    public function __construct(
        public readonly int $resalePriceIdr,
        public readonly int $pbjtTaxIdr,
        public readonly int $platformFeeIdr,
        public readonly int $promoterRoyaltyIdr,
        public readonly int $sellerPayoutIdr
    ) {
        if ($this->resalePriceIdr <= 0) {
            throw new \InvalidArgumentException('resalePriceIdr harus positif.');
        }

        $sum = $this->pbjtTaxIdr + $this->platformFeeIdr + $this->promoterRoyaltyIdr + $this->sellerPayoutIdr;
        if ($sum !== $this->resalePriceIdr) {
            throw new \DomainException("Pecahan alokasi tidak seimbang dengan nilai transaksi total ({$sum} != {$this->resalePriceIdr})");
        }
    }

    public static function createFromGross(int $grossPriceIdr): self
    {
        $pbjt = (int) intdiv($grossPriceIdr * self::PBJT_BPS, 10000);
        $platform = (int) intdiv($grossPriceIdr * self::PLATFORM_BPS, 10000);
        $promoter = (int) intdiv($grossPriceIdr * self::PROMOTER_BPS, 10000);
        $seller = $grossPriceIdr - ($pbjt + $platform + $promoter);

        return new self($grossPriceIdr, $pbjt, $platform, $promoter, $seller);
    }

    public function getEffectiveTaxRatePercentage(): float
    {
        return round(($this->pbjtTaxIdr / $this->resalePriceIdr) * 100, 2);
    }

    public function getEffectivePlatformRatePercentage(): float
    {
        return round(($this->platformFeeIdr / $this->resalePriceIdr) * 100, 2);
    }

    public function getEffectiveSellerPayoutPercentage(): float
    {
        return round(($this->sellerPayoutIdr / $this->resalePriceIdr) * 100, 2);
    }

    /**
     * @return array<string, int|float>
     */
    public function toDetailedArray(): array
    {
        return [
            'gross_resale_price_idr' => $this->resalePriceIdr,
            'pbjt_tax_idr' => $this->pbjtTaxIdr,
            'platform_fee_idr' => $this->platformFeeIdr,
            'promoter_royalty_idr' => $this->promoterRoyaltyIdr,
            'seller_payout_idr' => $this->sellerPayoutIdr,
            'pbjt_percentage' => $this->getEffectiveTaxRatePercentage(),
            'platform_percentage' => $this->getEffectivePlatformRatePercentage(),
            'seller_percentage' => $this->getEffectiveSellerPayoutPercentage(),
        ];
    }
}
