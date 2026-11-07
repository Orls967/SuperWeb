<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms;

use InvalidArgumentException;

final class TierCountdownPriceCertificateVerifier
{
    private const SECRET = 'venue-tier-countdown-secret-v1';

    public static function sign(int $base, int $demand, int $rain, int $floor, int $ceiling, int $calc, int $final): string
    {
        $payload = "{$base}:{$demand}:{$rain}:{$floor}:{$ceiling}:{$calc}:{$final}";

        return hash_hmac('sha256', $payload, self::SECRET);
    }

    public static function verify(TierCountdownPriceCertificate $certificate): bool
    {
        $expectedSignature = self::sign(
            $certificate->basePriceIdr,
            $certificate->demandBps,
            $certificate->rainProbabilityBps,
            $certificate->floorPriceIdr,
            $certificate->ceilingPriceIdr,
            $certificate->calculatedPriceIdr,
            $certificate->finalPriceIdr
        );

        if (! hash_equals($expectedSignature, $certificate->hmacSignature)) {
            throw new InvalidArgumentException('TierCountdownPriceCertificate HMAC signature is invalid');
        }

        if ($certificate->finalPriceIdr < $certificate->floorPriceIdr || $certificate->finalPriceIdr > $certificate->ceilingPriceIdr) {
            throw new InvalidArgumentException('TierCountdownPriceCertificate final price violates floor/ceiling boundaries');
        }

        return true;
    }
}
