<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Aviation;

use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;

final class DangerousGoodsCertificateVerifier
{
    public static function verify(DangerousGoodsResult $result): void
    {
        $cert = $result->certificate;

        // Invarian 1: isEligible true jika dan hanya jika violations kosong
        if ($cert->isEligible && count($cert->violations) > 0) {
            throw new InvalidCertificateException('Sertifikat tidak valid: dinyatakan lolos DG padahal ada pelanggaran regulasi.');
        }

        // Invarian 2: isEligible false harus punya rincian pelanggaran
        if (! $cert->isEligible && count($cert->violations) === 0) {
            throw new InvalidCertificateException('Sertifikat tidak valid: dinyatakan tidak lolos tanpa rincian pelanggaran.');
        }

        // Invarian 3: Berat tidak boleh negatif
        if ($cert->weightGrams < 0) {
            throw new InvalidCertificateException('Berat paket kargo tidak boleh negatif.');
        }
    }
}
