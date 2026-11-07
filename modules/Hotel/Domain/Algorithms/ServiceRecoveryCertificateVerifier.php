<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

use InvalidArgumentException;

final class ServiceRecoveryCertificateVerifier
{
    public static function verify(ServiceRecoveryCertificate $certificate): bool
    {
        if ($certificate->compensationAmountIdr < 0 || $certificate->compensationAmountIdr > $certificate->nightRateIdr) {
            throw new InvalidArgumentException('Sertifikat kompensasi recovery tidak valid: nominal melebihi batas tarif malam.');
        }
        if ($certificate->excessHours > 6.0 && ! $certificate->grantsFreeNight) {
            throw new InvalidArgumentException('Sertifikat kompensasi recovery tidak valid: keterlambatan > 6 jam wajib memperoleh sertifikat malam gratis.');
        }

        return true;
    }
}
