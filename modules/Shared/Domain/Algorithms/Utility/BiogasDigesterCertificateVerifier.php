<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Utility;

use RuntimeException;

/**
 * Verifikator sertifikat kriptografis untuk hasil kalkulasi Biogas Digester WTE.
 */
final class BiogasDigesterCertificateVerifier
{
    /**
     * @throws RuntimeException Bila sertifikat rusak atau tidak cocok
     */
    public function verify(BiogasDigesterResult $result): bool
    {
        $expectedHash = hash('sha256', $result->canonicalPayload);

        if (! hash_equals($expectedHash, $result->certificateHash)) {
            throw new RuntimeException(
                "Sertifikat Biogas WTE terkorupsi: Hash {$result->certificateHash} != {$expectedHash}"
            );
        }

        return true;
    }
}
