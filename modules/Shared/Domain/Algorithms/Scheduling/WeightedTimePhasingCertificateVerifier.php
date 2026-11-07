<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Scheduling;

use DomainException;

final class WeightedTimePhasingCertificateVerifier
{
    /**
     * Memverifikasi validitas sertifikat algoritma WeightedTimePhasing.
     * Syarat:
     * 1. Jumlah alokasi harian persis sama dengan total target (Σ = total)
     * 2. Selisih absolut antara alokasi integer dan kuota eksak < 1 (|alokasi - kuota| < 1)
     * 3. Kumulatif kurva-S adalah jumlahan berjalan yang tepat
     * 4. Hash sertifikat SHA-256 cocok
     */
    public function verify(WeightedTimePhasingResult $result): bool
    {
        $sum = 0;
        $cumulative = 0;
        $count = count($result->dailyAllocations);

        if ($count !== count($result->cumulativeSCurve) || $count !== count($result->exactQuotas)) {
            throw new DomainException('Dimensi data alokasi, kurva-S, dan kuota eksak tidak konsisten');
        }

        for ($i = 0; $i < $count; $i++) {
            $alloc = $result->dailyAllocations[$i];
            $quota = $result->exactQuotas[$i];
            $diff = abs($alloc - $quota);

            if ($diff >= 1.0) {
                throw new DomainException("Pelanggaran sertifikat: deviasi alokasi ke-{$i} ({$diff}) >= 1.0");
            }

            $sum += $alloc;
            $cumulative += $alloc;

            if ($result->cumulativeSCurve[$i] !== $cumulative) {
                throw new DomainException("Pelanggaran sertifikat: kurva-S kumulatif indeks {$i} tidak valid");
            }
        }

        if ($sum !== $result->totalTarget) {
            throw new DomainException("Pelanggaran sertifikat: total alokasi ({$sum}) tidak sama dengan total target ({$result->totalTarget})");
        }

        $expectedHash = hash('sha256', json_encode($result->certificateData, JSON_THROW_ON_ERROR));
        if ($expectedHash !== $result->certificateHash) {
            throw new DomainException('Pelanggaran sertifikat: tanda tangan hash sertifikat korup atau tidak cocok');
        }

        return true;
    }
}

