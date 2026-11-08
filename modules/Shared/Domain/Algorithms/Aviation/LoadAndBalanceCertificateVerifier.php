<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Aviation;

use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;

final class LoadAndBalanceCertificateVerifier
{
    public static function verify(LoadAndBalanceResult $result): void
    {
        $cert = $result->certificate;

        // Invarian 1: isWithinEnvelope true jika dan hanya jika violations kosong
        if ($cert->isWithinEnvelope && count($cert->violations) > 0) {
            throw new InvalidCertificateException('Sertifikat tidak valid: dinyatakan dalam amplop padahal ada pelanggaran batas.');
        }

        // Invarian 2: isWithinEnvelope false harus punya pelanggaran
        if (! $cert->isWithinEnvelope && count($cert->violations) === 0) {
            throw new InvalidCertificateException('Sertifikat tidak valid: dinyatakan melanggar amplop tanpa rincian pelanggaran.');
        }

        // Invarian 3: Perhitungan CG harus konsisten dengan floor(moment / weight)
        $expectedCg = ($cert->totalWeightKg > 0) ? (int) floor($cert->totalMoment / $cert->totalWeightKg) : 0;
        if ($cert->cgMm !== $expectedCg) {
            throw new InvalidCertificateException('Kalkulasi CG tidak sesuai dengan momen dan berat.');
        }
    }
}

