<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Islamic;

use RuntimeException;

final class GigCreditLimitCertificateVerifier
{
    /**
     * @param  array{payload: array<string, mixed>, hash: string, created_at: string}  $certificate
     */
    public static function verify(array $certificate): bool
    {
        if (! isset($certificate['payload'], $certificate['hash'])) {
            throw new RuntimeException('Struktur sertifikat GigCreditLimit tidak lengkap.');
        }

        $expectedHash = hash('sha256', json_encode($certificate['payload'], JSON_THROW_ON_ERROR));

        if (! hash_equals($expectedHash, (string) $certificate['hash'])) {
            throw new RuntimeException("Sertifikat GigCreditLimit rusak atau dipalsukan. Hash {$certificate['hash']} !== {$expectedHash}");
        }

        return true;
    }
}
