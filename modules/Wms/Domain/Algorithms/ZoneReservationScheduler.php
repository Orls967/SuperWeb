<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Algorithms;

use InvalidArgumentException;
use Modules\Wms\Domain\Certificates\ZoneReservationCertificate;

/**
 * Algoritma J1: Penjadwalan reservasi zona lalu lintas berkapasitas k dengan interval setengah-terbuka [start, end).
 *
 * Kompleksitas: O(N log N + N * H) dengan N = jumlah interval reservasi + blokir staf dan H = jumlah titik kandidat.
 * Aritmetika: 100% bilangan bulat (menit diskrit). Pembulatan: tidak ada.
 * Batas: duration >= 1, capacity >= 1, earliestStart >= 0.
 */
final class ZoneReservationScheduler
{
    public const string ERROR_INVALID_DURATION = 'INVALID_DURATION';

    public const string ERROR_INVALID_CAPACITY = 'INVALID_CAPACITY';

    /**
     * @param  int  $capacity  Kapasitas zona k >= 1
     * @param  list<array{start: int, end: int}>  $reservations  Interval setengah-terbuka [start, end)
     * @param  list<array{start: int, end: int}>  $staffBlocks  Interval blokir staf [start, end)
     * @param  int  $duration  Durasi d >= 1
     * @param  int  $earliestStart  Waktu mulai paling awal t0 >= 0
     * @return array{
     *     start: int,
     *     end: int,
     *     interval: array{start: int, end: int},
     *     reservations: list<array{start: int, end: int}>,
     *     certificate: ZoneReservationCertificate
     * }
     */
    public function schedule(
        int $capacity,
        array $reservations,
        array $staffBlocks,
        int $duration,
        int $earliestStart,
    ): array {
        if ($duration <= 0) {
            throw new InvalidArgumentException(self::ERROR_INVALID_DURATION);
        }
        if ($capacity <= 0) {
            throw new InvalidArgumentException(self::ERROR_INVALID_CAPACITY);
        }

        $t0 = max(0, $earliestStart);

        $normRes = array_values(array_map(
            static fn (array $r): array => ['start' => (int) $r['start'], 'end' => (int) $r['end']],
            $reservations,
        ));
        usort($normRes, static fn (array $a, array $b): int => [$a['start'], $a['end']] <=> [$b['start'], $b['end']]);

        $normBlocks = array_values(array_map(
            static fn (array $b): array => ['start' => (int) $b['start'], 'end' => (int) $b['end']],
            $staffBlocks,
        ));
        usort($normBlocks, static fn (array $a, array $b): int => [$a['start'], $a['end']] <=> [$b['start'], $b['end']]);

        // Kandidat titik awal t adalah t0 dan seluruh titik akhir interval yang >= t0.
        $candidates = [$t0];
        foreach ($normRes as $r) {
            if ($r['end'] > $t0) {
                $candidates[] = $r['end'];
            }
        }
        foreach ($normBlocks as $b) {
            if ($b['end'] > $t0) {
                $candidates[] = $b['end'];
            }
        }
        $candidates = array_values(array_unique($candidates));
        sort($candidates);

        $steps = [];
        $chosenStart = null;

        foreach ($candidates as $t) {
            $windowEnd = $t + $duration;
            $conflictReason = null;

            foreach ($normBlocks as $block) {
                if ($t < $block['end'] && $windowEnd > $block['start']) {
                    $conflictReason = "staff_block:[{$block['start']},{$block['end']})";
                    break;
                }
            }

            if ($conflictReason === null) {
                // Periksa setiap titik perubahan dalam [t, t + duration)
                $checkPoints = [$t];
                foreach ($normRes as $r) {
                    if ($r['start'] > $t && $r['start'] < $windowEnd) {
                        $checkPoints[] = $r['start'];
                    }
                    if ($r['end'] > $t && $r['end'] < $windowEnd) {
                        $checkPoints[] = $r['end'];
                    }
                }
                $checkPoints = array_values(array_unique($checkPoints));
                sort($checkPoints);

                foreach ($checkPoints as $cp) {
                    $active = 0;
                    foreach ($normRes as $r) {
                        if ($cp >= $r['start'] && $cp < $r['end']) {
                            $active++;
                        }
                    }
                    if ($active >= $capacity) {
                        $conflictReason = "capacity_full_at:{$cp}({$active}/{$capacity})";
                        break;
                    }
                }
            }

            $steps[] = [
                'candidate_t' => $t,
                'window_end' => $windowEnd,
                'accepted' => $conflictReason === null,
                'reason' => $conflictReason ?? 'feasible',
            ];

            if ($conflictReason === null) {
                $chosenStart = $t;
                break;
            }
        }

        // Secara matematis selalu ada t yang layak karena jumlah interval berhingga; fallback di ujung maksimum.
        if ($chosenStart === null) {
            $maxEnd = $t0;
            foreach ($normRes as $r) {
                $maxEnd = max($maxEnd, $r['end']);
            }
            foreach ($normBlocks as $b) {
                $maxEnd = max($maxEnd, $b['end']);
            }
            $chosenStart = $maxEnd;
        }

        $chosenEnd = $chosenStart + $duration;
        $newInterval = ['start' => $chosenStart, 'end' => $chosenEnd];
        $updatedReservations = $normRes;
        $updatedReservations[] = $newInterval;
        usort($updatedReservations, static fn (array $a, array $b): int => [$a['start'], $a['end']] <=> [$b['start'], $b['end']]);

        $canonicalInput = [
            'capacity' => $capacity,
            'duration' => $duration,
            'earliest_start' => $t0,
            'reservations' => $normRes,
            'staff_blocks' => $normBlocks,
        ];
        $resultSummary = [
            'start' => $chosenStart,
            'end' => $chosenEnd,
        ];

        $certificate = ZoneReservationCertificate::issue($canonicalInput, $steps, $resultSummary);
        $certificate->verifyOrFail();

        return [
            'start' => $chosenStart,
            'end' => $chosenEnd,
            'interval' => $newInterval,
            'reservations' => $updatedReservations,
            'certificate' => $certificate,
        ];
    }
}
