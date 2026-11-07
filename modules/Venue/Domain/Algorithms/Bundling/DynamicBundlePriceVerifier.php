<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms\Bundling;

final class DynamicBundlePriceVerifier
{
    private const SECRET = 'dynamic-bundle-v10-secret-key-2026';

    public static function sign(int $floorCost, int $basePrice, int $surgeBps, int $leadDiscountBps, int $finalPrice): string
    {
        $payload = sprintf('%d:%d:%d:%d:%d', $floorCost, $basePrice, $surgeBps, $leadDiscountBps, $finalPrice);

        return hash_hmac('sha256', $payload, self::SECRET);
    }

    public static function verify(DynamicBundlePriceCertificate $cert): bool
    {
        $expected = self::sign(
            $cert->totalFloorCostIdr,
            $cert->totalBasePriceIdr,
            $cert->surgeBps,
            $cert->leadTimeDiscountBps,
            $cert->finalBundlePriceIdr
        );

        return hash_equals($expected, $cert->hmacSignature);
    }
}
