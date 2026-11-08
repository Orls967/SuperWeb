<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Aviation;

use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;

final class AirworthinessCertificateVerifier
{
    public static function verify(AirworthinessResult $result): void
    {
        $cert = $result->certificate;

        // Invarian 1: Izin true hanya jika reasons kosong
        if ($cert->isPermitted && count($cert->reasons) > 0) {
            throw new InvalidCertificateException('Sertifikat tidak valid: izin diberikan padahal ada alasan penolakan.');
        }

        // Invarian 2: Izin false harus memiliki minimal 1 alasan penolakan
        if (! $cert->isPermitted && count($cert->reasons) === 0) {
            throw new InvalidCertificateException('Sertifikat tidak valid: izin ditolak tanpa alasan penolakan.');
        }

        // Invarian 3: Reasons harus terurut alfabetis
        $sorted = $cert->reasons;
        sort($sorted);
        if ($sorted !== $cert->reasons) {
            throw new InvalidCertificateException('Alasan penolakan tidak terurut alfabetis.');
        }
    }
}
