<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Reinsurance;

use RuntimeException;

final class TakafulSurplusCertificateVerifier
{
    private const string HMAC_SECRET = 'antigravity:takaful:surplus:cert:2026';

    /**
     * Memverifikasi validitas sertifikat kalkulasi surplus takaful.
     */
    public function verify(TakafulSurplusResult $result): bool
    {
        $canonical = sprintf(
            'TAKAFUL-SURPLUS:contrib=%d:claims=%d:res=%d:surp=%d:part=%d:cont=%d:op=%d',
            $result->netContributionIdr,
            $result->netIncurredClaimsIdr,
            $result->technicalReserveIdr,
            $result->grossSurplusIdr,
            $result->participantSurplusShareIdr,
            $result->contingencyReserveShareIdr,
            $result->mudharabahOperatorShareIdr
        );

        $expected = hash_hmac('sha256', $canonical, self::HMAC_SECRET);

        if (! hash_equals($expected, $result->certificate)) {
            throw new RuntimeException('Sertifikat surplus underwriting Takaful tidak valid atau telah dimanipulasi!');
        }

        return true;
    }
}
