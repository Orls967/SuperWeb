<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Algorithms\Certificates;

use Modules\Intercompany\Domain\Exceptions\InvalidCertificateException;

final class BilateralEliminationCertificateVerifier
{
    public static function verify(BilateralEliminationCertificate $certificate): bool
    {
        return hash_equals($certificate->computeHash(), $certificate->sha256);
    }

    public static function assertValid(BilateralEliminationCertificate $certificate): void
    {
        if (! self::verify($certificate)) {
            throw new InvalidCertificateException('Bilateral Elimination Certificate hash mismatch.');
        }
    }
}
