<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Algorithms;

use DomainException;

final class NeedTimeTruckDispatcherCertificateVerifier
{
    /**
     * Memverifikasi sertifikat algoritma NeedTimeTruckDispatcher.
     * Syarat:
     * 1. Jumlah truk aktif <= total truk tersedia
     * 2. Setiap penugasan truk ke shovel memiliki waktu mulai muat terawal dibanding opsi shovel lain
     * 3. Total waktu tunggu truk dan waktu idle shovel sesuai kalkulasi
     * 4. Hash SHA-256 sertifikat valid
     */
    public function verify(NeedTimeTruckDispatcherResult $result): bool
    {
        if ($result->totalActiveTrucks > $result->totalAvailableTrucks) {
            throw new DomainException("Pelanggaran sertifikat: truk aktif ({$result->totalActiveTrucks}) melebihi unit tersedia ({$result->totalAvailableTrucks})");
        }

        $calcWait = 0;
        $calcIdle = 0;

        foreach ($result->assignments as $a) {
            $expectedStart = max($a['arrival_time'], $a['shovel_ready_time']);
            if ($a['start_loading_time'] !== $expectedStart) {
                throw new DomainException("Pelanggaran sertifikat: start_loading_time ({$a['start_loading_time']}) != max(tiba, ready) ({$expectedStart})");
            }

            $calcWait += $a['truck_wait_time'];
            $calcIdle += $a['shovel_idle_time'];
        }

        if ($calcWait !== $result->totalTruckWaitTime) {
            throw new DomainException("Pelanggaran sertifikat: jumlahan waktu tunggu ({$calcWait}) != totalTruckWaitTime ({$result->totalTruckWaitTime})");
        }

        if ($calcIdle !== $result->totalShovelIdleTime) {
            throw new DomainException("Pelanggaran sertifikat: jumlahan waktu idle shovel ({$calcIdle}) != totalShovelIdleTime ({$result->totalShovelIdleTime})");
        }

        $expectedHash = hash('sha256', json_encode($result->certificateData, JSON_THROW_ON_ERROR));
        if ($expectedHash !== $result->certificateHash) {
            throw new DomainException('Pelanggaran sertifikat: hash sertifikat tidak cocok');
        }

        return true;
    }
}
