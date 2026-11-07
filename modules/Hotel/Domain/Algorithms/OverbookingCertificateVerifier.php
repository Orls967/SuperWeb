<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

use InvalidArgumentException;

final class OverbookingCertificateVerifier
{
    public static function verify(OverbookingCertificate $certificate): bool
    {
        if ($certificate->optimalK < 0 || $certificate->optimalK > $certificate->policyCeilingK) {
            throw new InvalidArgumentException("Sertifikat overbooking tidak valid: k optimal melampaui plafon kebijakan.");
        }
        if (!isset($certificate->valuesByK[$certificate->optimalK])) {
            throw new InvalidArgumentException("Sertifikat overbooking tidak valid: indeks k tidak ditemukan pada kurva nilai.");
        }
        if ($certificate->valuesByK[$certificate->optimalK] !== $certificate->expectedNetValueIdr) {
            throw new InvalidArgumentException("Sertifikat overbooking tidak valid: nilai ekspektasi tidak konsisten.");
        }
        // Pastikan optimalK memaksimalkan nilai pada jangkauan [0, policyCeilingK]
        for ($k = 0; $k <= $certificate->policyCeilingK && $k < count($certificate->valuesByK); $k++) {
            if ($certificate->valuesByK[$k] > $certificate->expectedNetValueIdr) {
                throw new InvalidArgumentException("Sertifikat overbooking tidak valid: k optimal bukan nilai maksimum.");
            }
        }
        return true;
    }
}
