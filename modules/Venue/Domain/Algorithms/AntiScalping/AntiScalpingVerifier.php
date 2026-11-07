<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms\AntiScalping;

final class AntiScalpingVerifier
{
    private const SECRET = 'anti-scalping-v9-secret-key-2026';

    public static function sign(int $faceValue, int $asking, int $effective, int $maxPrice, int $transfers, int $active): string
    {
        $payload = sprintf('%d:%d:%d:%d:%d:%d', $faceValue, $asking, $effective, $maxPrice, $transfers, $active);

        return hash_hmac('sha256', $payload, self::SECRET);
    }

    public static function verify(AntiScalpingCertificate $cert): bool
    {
        $expected = self::sign(
            $cert->originalFaceValueIdr,
            $cert->askingPriceIdr,
            $cert->effectivePriceIdr,
            $cert->maxAllowedPriceIdr,
            $cert->transferCount,
            $cert->userActiveTicketCount
        );

        return hash_equals($expected, $cert->hmacSignature);
    }
}
