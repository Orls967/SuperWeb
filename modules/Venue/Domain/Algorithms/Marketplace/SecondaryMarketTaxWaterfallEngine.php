<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms\Marketplace;

class SecondaryMarketTaxWaterfallEngine
{
    /**
     * Membagi hasil transaksi penjualan kembali (resale) tiket secara tertutup integer moneter:
     * 1. Pajak Hiburan / PBJT (10%)
     * 2. Biaya Layanan Platform (8%)
     * 3. Royalti Promotor / Hak Cipta Artis (2%)
     * 4. Sisa Bersih Pembayaran ke Penjual (Seller Net Payout)
     */
    public function calculate(SecondaryMarketTaxWaterfallInput $input): SecondaryMarketTaxWaterfallResult
    {
        return $this->calculateWaterfall($input);
    }

    public function calculateWaterfall(SecondaryMarketTaxWaterfallInput $input): SecondaryMarketTaxWaterfallResult
    {
        $taxPbjtIdr = intdiv($input->resalePriceIdr * $input->taxRateBps, 10000);
        $platformFeeIdr = intdiv($input->resalePriceIdr * $input->platformFeeRateBps, 10000);
        $promoterRoyaltyIdr = intdiv($input->resalePriceIdr * $input->promoterRoyaltyBps, 10000);

        // Konservasi tertutup: Sisa bersih penjual adalah selisih eksak agar total tidak ada pembulatan menguap
        $sellerPayoutIdr = $input->resalePriceIdr - ($taxPbjtIdr + $platformFeeIdr + $promoterRoyaltyIdr);

        $signature = SecondaryMarketTaxWaterfallVerifier::sign(
            $input->resalePriceIdr,
            $taxPbjtIdr,
            $platformFeeIdr,
            $promoterRoyaltyIdr,
            $sellerPayoutIdr
        );

        $cert = new SecondaryMarketTaxWaterfallCertificate(
            resalePriceIdr: $input->resalePriceIdr,
            taxPbjtIdr: $taxPbjtIdr,
            platformFeeIdr: $platformFeeIdr,
            promoterRoyaltyIdr: $promoterRoyaltyIdr,
            sellerPayoutIdr: $sellerPayoutIdr,
            hmacSignature: $signature
        );

        return new SecondaryMarketTaxWaterfallResult(
            taxPbjtIdr: $taxPbjtIdr,
            platformFeeIdr: $platformFeeIdr,
            promoterRoyaltyIdr: $promoterRoyaltyIdr,
            sellerPayoutIdr: $sellerPayoutIdr,
            certificate: $cert
        );
    }
}
