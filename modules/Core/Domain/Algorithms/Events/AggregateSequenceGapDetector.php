<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Events;

/**
 * Algoritma A4: AggregateSequenceGapDetector [Fase R8]
 *
 * Mendeteksi celah nomor urut (gaps) dan duplikasi nomor urut (duplicates)
 * per deret nomor urut aggregate event stream (dimulai dari 1).
 *
 * Kompleksitas: O(n log n) berbasis sorting array sequence bilangan bulat.
 * Tidak mengandalkan bilangan floating point; murni aritmetika integer.
 */
class AggregateSequenceGapDetector
{
    /**
     * Deteksi rentang celah dan duplikasi nomor urut.
     *
     * @param  array<int, int>  $sequences  Daftar integer nomor urut
     * @return array{gaps: array<int, array{0: int, 1: int}>, duplicates: array<int, int>}
     */
    public function detect(array $sequences): array
    {
        if (empty($sequences)) {
            return [
                'gaps' => [],
                'duplicates' => [],
            ];
        }

        // Hitung frekuensi nomor urut untuk mendeteksi duplikat
        $counts = [];
        $uniqueNumbers = [];

        foreach ($sequences as $num) {
            $n = (int) $num;
            if (! isset($counts[$n])) {
                $counts[$n] = 0;
                $uniqueNumbers[] = $n;
            }
            $counts[$n]++;
        }

        // Kumpulkan duplikat terurut menaik
        $duplicates = [];
        foreach ($counts as $num => $freq) {
            if ($freq > 1) {
                $duplicates[] = $num;
            }
        }
        sort($duplicates, SORT_NUMERIC);

        // Urutkan nomor unik
        sort($uniqueNumbers, SORT_NUMERIC);

        $gaps = [];
        $expected = 1;

        foreach ($uniqueNumbers as $actual) {
            if ($actual > $expected) {
                // Ada celah antara $expected dan $actual - 1
                $gaps[] = [$expected, $actual - 1];
            }
            $expected = $actual + 1;
        }

        return [
            'gaps' => $gaps,
            'duplicates' => $duplicates,
        ];
    }
}
