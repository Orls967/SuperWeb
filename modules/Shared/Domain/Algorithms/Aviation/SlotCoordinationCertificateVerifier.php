<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Aviation;

use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;

final class SlotCoordinationCertificateVerifier
{
    public static function verify(SlotCoordinationResult $result): void
    {
        $cert = $result->certificate;

        // Invarian 1: Kapasitas >= totalRetained + allocated + remainingPool
        $totalAlloc = array_sum($result->allocated);
        if ($cert->capacity < ($cert->totalRetained + $totalAlloc + $cert->remainingPool)) {
            throw new InvalidCertificateException('Total alokasi melebihi kapasitas bandara.');
        }

        // Invarian 2: Kolam = kapasitas - totalRetained
        if ($cert->pool !== ($cert->capacity - $cert->totalRetained)) {
            throw new InvalidCertificateException('Perhitungan kolam slot tidak konsisten.');
        }

        // Invarian 3: Sisa kolam >= 0
        if ($cert->remainingPool < 0) {
            throw new InvalidCertificateException('Sisa kolam tidak boleh negatif.');
        }
    }
}
