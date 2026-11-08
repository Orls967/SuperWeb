<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Aviation;

use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;

final class FareRefundCertificateVerifier
{
    public static function verify(FareRefundResult $result): void
    {
        $cert = $result->certificate;

        // Invarian 1: Total refund = base refund + tax refund
        if ($cert->totalRefundMinor !== ($cert->baseRefundMinor + $cert->taxRefundMinor)) {
            throw new InvalidCertificateException('Total refund tidak sesuai dengan penjumlahan dasar dan pajak.');
        }

        // Invarian 2: Base refund tidak boleh melebihi base fare
        if ($cert->baseRefundMinor > $cert->baseFareMinor) {
            throw new InvalidCertificateException('Refund dasar melebihi tarif dasar tiket.');
        }

        // Invarian 3: Tax refund tidak boleh negatif
        if ($cert->taxRefundMinor < 0 || $cert->baseRefundMinor < 0) {
            throw new InvalidCertificateException('Nilai refund tidak boleh negatif.');
        }
    }
}

