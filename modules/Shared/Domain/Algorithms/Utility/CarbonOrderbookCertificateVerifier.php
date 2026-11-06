<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Utility;

use RuntimeException;

/**
 * Memverifikasi integritas hash sertifikat pencocokan orderbook kredit karbon.
 */
final class CarbonOrderbookCertificateVerifier
{
    /**
     * @throws RuntimeException Jika verifikasi gagal
     */
    public function verify(CarbonOrderbookMatchingResult $result): bool
    {
        $expectedHash = hash('sha256', $result->canonicalPayload);

        if (! hash_equals($expectedHash, $result->certificateHash)) {
            throw new RuntimeException(
                "Sertifikat Carbon Orderbook terkorupsi: {$result->certificateHash} != {$expectedHash}"
            );
        }

        return true;
    }
}
