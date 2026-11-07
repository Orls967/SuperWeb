<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

use InvalidArgumentException;

final class HvacSetbackCertificateVerifier
{
    public static function verify(HvacSetbackCertificate $certificate): bool
    {
        if ($certificate->energySavedWh < 0) {
            throw new InvalidArgumentException('Sertifikat HVAC setback tidak valid: nilai penghematan energi negatif.');
        }
        if (! $certificate->setbackActive && $certificate->energySavedWh > 0) {
            throw new InvalidArgumentException('Sertifikat HVAC setback tidak valid: penghematan energi terdeteksi saat mode setback tidak aktif.');
        }

        return true;
    }
}
