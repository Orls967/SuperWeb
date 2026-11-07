<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms\Marketplace;

final class SecondaryMarketTaxWaterfallVerifier
{
    private const SECRET = 'secondary-tax-waterfall-v12-secret-2026';

    public static function sign(int $resale, int $tax, int $fee, int $royalty, int $payout): string
    {
        $payload = sprintf('%d:%d:%d:%d:%d', $resale, $tax, $fee, $royalty, $payout);

        return hash_hmac('sha256', $payload, self::SECRET);
    }

    public static function verify(SecondaryMarketTaxWaterfallCertificate $cert): bool
    {
        $expected = self::sign(
            $cert->resalePriceIdr,
            $cert->taxPbjtIdr,
            $cert->platformFeeIdr,
            $cert->promoterRoyaltyIdr,
            $cert->sellerPayoutIdr
        );

        return hash_equals($expected, $cert->hmacSignature);
    }
}
