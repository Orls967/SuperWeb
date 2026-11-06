<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Utility;

use RuntimeException;

/**
 * Memverifikasi keabsahan sertifikat komputasi CBAM Uni Eropa.
 */
final class CbamEmissionsCertificateVerifier
{
    /**
     * @throws RuntimeException Bila sertifikat rusak
     */
    public function verify(CbamEmissionsTariffResult $result): bool
    {
        $expectedHash = hash('sha256', $result->canonicalPayload);

        if (! hash_equals($expectedHash, $result->certificateHash)) {
            throw new RuntimeException(
                "Sertifikat CBAM terkorupsi: Hash {$result->certificateHash} != {$expectedHash}"
            );
        }

        return true;
    }
}
