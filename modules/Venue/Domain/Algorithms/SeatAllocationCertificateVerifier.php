<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms;

use RuntimeException;

class SeatAllocationCertificateVerifier
{
    /**
     * @throws RuntimeException Bila sertifikat tidak valid atau tidak cocok dengan hasil
     */
    public function verify(SeatAllocationCertificate $certificate, SeatAllocationResult $result): bool
    {
        if (! $certificate->isValid()) {
            throw new RuntimeException('Sertifikat alokasi kursi tidak valid: properti internal sertifikat korup');
        }

        if (count($result->seats) !== $certificate->quantity) {
            throw new RuntimeException('Sertifikat alokasi kursi tidak valid: jumlah kursi tidak cocok');
        }

        $resultIds = array_column($result->seats, 'id');
        $resultNumbers = array_column($result->seats, 'seat_number');
        $resultRows = array_unique(array_column($result->seats, 'row_label'));

        if ($resultIds !== $certificate->seatIds) {
            throw new RuntimeException('Sertifikat alokasi kursi tidak valid: ID kursi tidak cocok dengan sertifikat');
        }

        if ($resultNumbers !== $certificate->seatNumbers) {
            throw new RuntimeException('Sertifikat alokasi kursi tidak valid: nomor kursi tidak cocok dengan sertifikat');
        }

        if (count($resultRows) !== 1 || reset($resultRows) !== $certificate->rowLabel) {
            throw new RuntimeException('Sertifikat alokasi kursi tidak valid: baris kursi tidak konsisten');
        }

        return true;
    }
}
