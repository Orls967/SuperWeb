<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Algorithms;

use InvalidArgumentException;
use Modules\Wms\Domain\ValueObjects\IntervalWindow;

/**
 * Kalkulator utilisasi & heatmap kepadatan zona lalu lintas dalam permil bulat (D279-6).
 *
 * Kompleksitas: O(R log R + B * R) dengan R = jumlah reservasi dan B = jumlah bucket waktu.
 * Aritmetika: 100% bilangan bulat (permil 0..1000 per kapasitas).
 */
final class ZoneCongestionHeatmapCalculator
{
    /**
     * @param  int  $capacityK  Kapasitas zona >= 1
     * @param  list<array{start: int, end: int}>  $reservations
     * @param  int  $horizonStartMin
     * @param  int  $horizonEndMin
     * @param  int  $bucketSizeMin
     * @return array{
     *     peak_active: int,
     *     peak_utilization_permil: int,
     *     average_utilization_permil: int,
     *     buckets: list<array{start_min: int, end_min: int, max_active: int, utilization_permil: int}>
     * }
     */
    public function analyze(
        int $capacityK,
        array $reservations,
        int $horizonStartMin = 0,
        int $horizonEndMin = 60,
        int $bucketSizeMin = 15,
    ): array {
        if ($capacityK <= 0 || $bucketSizeMin <= 0 || $horizonEndMin <= $horizonStartMin) {
            throw new InvalidArgumentException('Parameter analisis kepadatan zona tidak valid.');
        }

        $windows = [];
        foreach ($reservations as $res) {
            $s = (int) $res['start'];
            $e = (int) $res['end'];
            if ($e > $s) {
                $windows[] = new IntervalWindow(max(0, $s), $e);
            }
        }

        $buckets = [];
        $peakActive = 0;
        $sumUtilPermil = 0;

        for ($t = $horizonStartMin; $t < $horizonEndMin; $t += $bucketSizeMin) {
            $bEnd = min($horizonEndMin, $t + $bucketSizeMin);
            $bWindow = new IntervalWindow($t, $bEnd);

            $maxActiveInBucket = 0;
            for ($m = $t; $m < $bEnd; $m++) {
                $activeAtM = 0;
                foreach ($windows as $w) {
                    if ($w->overlaps($bWindow) && $w->containsMinute($m)) {
                        $activeAtM++;
                    }
                }
                $maxActiveInBucket = max($maxActiveInBucket, $activeAtM);
            }

            $utilPermil = intdiv($maxActiveInBucket * 1000, $capacityK);
            $peakActive = max($peakActive, $maxActiveInBucket);
            $sumUtilPermil += $utilPermil;

            $buckets[] = [
                'start_min' => $t,
                'end_min' => $bEnd,
                'max_active' => $maxActiveInBucket,
                'utilization_permil' => $utilPermil,
            ];
        }

        $bucketCount = max(1, count($buckets));

        return [
            'peak_active' => $peakActive,
            'peak_utilization_permil' => intdiv($peakActive * 1000, $capacityK),
            'average_utilization_permil' => intdiv($sumUtilPermil, $bucketCount),
            'buckets' => $buckets,
        ];
    }
}
