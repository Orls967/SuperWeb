<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Blasting;

final class KcoBlastingFragmentationVerifier
{
    private const SECRET = 'kco-blasting-fragmentation-secret-key-2026';

    public static function sign(
        float $x50Mm,
        float $uniformityN,
        float $oversizePct,
        float $finesPct,
        float $powderFactor
    ): string {
        $payload = sprintf(
            'x50:%.2f|n:%.3f|ovs:%.2f|fin:%.2f|pf:%.3f',
            $x50Mm,
            $uniformityN,
            $oversizePct,
            $finesPct,
            $powderFactor
        );

        return hash_hmac('sha256', $payload, self::SECRET);
    }

    public static function verify(
        KcoBlastingFragmentationModelResult $result
    ): bool {
        $expected = self::sign(
            $result->meanFragmentSizeX50Mm,
            $result->uniformityIndexN,
            $result->oversizePercent,
            $result->finesPercent,
            $result->powderFactorKgPerM3
        );

        return hash_equals($expected, $result->hmacSignature);
    }
}
