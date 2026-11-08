<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Aviation;

/**
 * A1: Aviation\SlotCoordinationAllocator
 *
 * Mengimplementasikan aturan IATA WASG 80/20 untuk alokasi slot bandara terkoordinasi:
 * - Slot historis dipertahankan jika pemakaian >= 80% (8.000 bps).
 * - Kolam = kapasitas - slot yang dipertahankan.
 * - Cadangan pendatang baru = ceil(50% * kolam).
 * - Permintaan pendatang baru dilayani dulu sampai cadangan habis (urut waktu minta, lalu kode maskapai).
 * - Sisa kolam melayani antrean umum menurut waktu minta.
 */
final class SlotCoordinationAllocator
{
    /**
     * @param  list<array{operator: string, held: int, usage_bps: int}>  $historicalSlots
     * @param  list<array{operator: string, requested: int, is_new_entrant: bool, request_time: int}>  $requests
     */
    public function allocate(int $capacity, array $historicalSlots, array $requests): SlotCoordinationResult
    {
        // 1. Evaluasi slot historis (Aturan 80/20)
        $retained = [];
        $totalRetained = 0;
        foreach ($historicalSlots as $hist) {
            $op = $hist['operator'];
            $held = $hist['held'];
            $usage = $hist['usage_bps'];
            if ($usage >= 8000) {
                $retained[$op] = $held;
                $totalRetained += $held;
            } else {
                $retained[$op] = 0;
            }
        }

        $pool = max(0, $capacity - $totalRetained);
        $newEntrantReserve = (int) ceil($pool * 0.5);

        // 2. Pisahkan permintaan pendatang baru dan umum
        $newEntrantRequests = [];
        $incumbentRequests = [];

        foreach ($requests as $req) {
            if ($req['is_new_entrant']) {
                $newEntrantRequests[] = $req;
            } else {
                $incumbentRequests[] = $req;
            }
        }

        // Urutkan pendatang baru: request_time asc, lalu operator asc
        usort($newEntrantRequests, function ($a, $b) {
            if ($a['request_time'] === $b['request_time']) {
                return strcmp($a['operator'], $b['operator']);
            }

            return $a['request_time'] <=> $b['request_time'];
        });

        // 3. Alokasikan untuk pendatang baru dari cadangan
        $allocated = [];
        $waitlisted = [];
        $reserveRemaining = $newEntrantReserve;
        $unfulfilledNewEntrants = [];

        foreach ($newEntrantRequests as $req) {
            $op = $req['operator'];
            $needed = $req['requested'];
            if ($reserveRemaining >= $needed) {
                $allocated[$op] = ($allocated[$op] ?? 0) + $needed;
                $reserveRemaining -= $needed;
            } elseif ($reserveRemaining > 0) {
                $give = $reserveRemaining;
                $allocated[$op] = ($allocated[$op] ?? 0) + $give;
                $reserveRemaining = 0;
                $unfulfilledNewEntrants[] = [
                    'operator' => $op,
                    'requested' => $needed - $give,
                    'is_new_entrant' => true,
                    'request_time' => $req['request_time'],
                ];
            } else {
                $unfulfilledNewEntrants[] = $req;
            }
        }

        $poolRemaining = $pool - ($newEntrantReserve - $reserveRemaining);

        // 4. Gabungkan sisa permintaan ke antrean umum (urut waktu minta, lalu operator)
        $generalQueue = array_merge($incumbentRequests, $unfulfilledNewEntrants);
        usort($generalQueue, function ($a, $b) {
            if ($a['request_time'] === $b['request_time']) {
                return strcmp($a['operator'], $b['operator']);
            }

            return $a['request_time'] <=> $b['request_time'];
        });

        // 5. Alokasi dari sisa kolam umum
        foreach ($generalQueue as $req) {
            $op = $req['operator'];
            $needed = $req['requested'];
            if ($poolRemaining >= $needed) {
                $allocated[$op] = ($allocated[$op] ?? 0) + $needed;
                $poolRemaining -= $needed;
            } elseif ($poolRemaining > 0) {
                $give = $poolRemaining;
                $allocated[$op] = ($allocated[$op] ?? 0) + $give;
                $waitlisted[$op] = ($waitlisted[$op] ?? 0) + ($needed - $give);
                $poolRemaining = 0;
            } else {
                $waitlisted[$op] = ($waitlisted[$op] ?? 0) + $needed;
            }
        }

        // Pastikan semua operator ada di output
        foreach ($requests as $req) {
            $op = $req['operator'];
            if (! isset($allocated[$op])) {
                $allocated[$op] = 0;
            }
            if (! isset($waitlisted[$op])) {
                $waitlisted[$op] = 0;
            }
        }

        $certificate = new SlotCoordinationCertificate(
            capacity: $capacity,
            totalRetained: $totalRetained,
            pool: $pool,
            allocated: $allocated,
            waitlisted: $waitlisted,
            remainingPool: $poolRemaining
        );

        $result = new SlotCoordinationResult(
            retained: $retained,
            allocated: $allocated,
            waitlisted: $waitlisted,
            remainingPool: $poolRemaining,
            certificate: $certificate
        );

        SlotCoordinationCertificateVerifier::verify($result);

        return $result;
    }
}
