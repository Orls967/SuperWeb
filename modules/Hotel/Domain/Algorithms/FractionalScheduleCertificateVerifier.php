<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

/**
 * Verifikator Matematis & Zero-Collision Invariant untuk FractionalScheduleCertificate.
 */
final class FractionalScheduleCertificateVerifier
{
    public function verify(FractionalScheduleCertificate $cert): bool
    {
        // 1. Verifikasi Kuota Minggu (1 Fraksi = 1 Minggu Hak Inap per tahun)
        if ($cert->totalFractionsOwned !== ($cert->allocatedWeeksCount + $cert->rentalPoolWeeksCount)) {
            return false;
        }

        if (count($cert->scheduledWeeks) !== $cert->totalFractionsOwned) {
            return false;
        }

        // 2. Verifikasi Zero-Collision (Tidak boleh ada minggu yang tumpang tindih)
        $seenWeeks = [];
        $ownerUsedCount = 0;
        $rentalPoolCount = 0;

        foreach ($cert->scheduledWeeks as $item) {
            $weekNum = $item['week_number'];
            if ($weekNum < 1 || $weekNum > 52) {
                return false;
            }
            if (isset($seenWeeks[$weekNum])) {
                return false; // Tabrakan minggu yang sama
            }
            $seenWeeks[$weekNum] = true;

            if ($item['status'] === 'OWNER_OCCUPIED') {
                $ownerUsedCount++;
            } elseif ($item['status'] === 'RENTAL_POOL') {
                $rentalPoolCount++;
            } else {
                return false;
            }
        }

        if ($ownerUsedCount !== $cert->allocatedWeeksCount || $rentalPoolCount !== $cert->rentalPoolWeeksCount) {
            return false;
        }

        // 3. Verifikasi Checksum Hash
        $expectedChecksum = hash('sha256', sprintf(
            '%s|%s|%d|%d|%d|%d',
            $cert->unitId,
            $cert->ownerId,
            $cert->totalFractionsOwned,
            $cert->allocatedWeeksCount,
            $cert->rentalPoolWeeksCount,
            count($cert->scheduledWeeks)
        ));

        return hash_equals($expectedChecksum, $cert->checksum);
    }
}
