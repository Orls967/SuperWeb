<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Utility;

use RuntimeException;

/**
 * Memverifikasi keaslian hash transisi status sertifikat REC.
 */
final class RecLifecycleCertificateVerifier
{
    /**
     * @throws RuntimeException Bila sertifikat rusak
     */
    public function verify(RecLifecycleResult $result): bool
    {
        $expectedHash = hash('sha256', $result->canonicalPayload);

        if (! hash_equals($expectedHash, $result->certificateHash)) {
            throw new RuntimeException(
                "Sertifikat siklus hidup REC terkorupsi: Hash {$result->certificateHash} != {$expectedHash}"
            );
        }

        return true;
    }
}
