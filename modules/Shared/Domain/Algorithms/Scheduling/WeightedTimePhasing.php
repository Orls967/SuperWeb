<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Scheduling;

use InvalidArgumentException;

/**
 * Algoritma M1: Pemecahan target produksi berbobot ke hari/shift (Weighted Time-Phasing)
 * Menggunakan metode pembagian Hamilton / Largest Remainder untuk menjamin
 * kuota bulat integer dan deviasi kuota < 1, dilengkapi kurva-S kumulatif.
 *
 * Kompleksitas Waktu: O(N log N) karena pengurutan sisa pecahan (N = jumlah hari/periode).
 * Kompleksitas Ruang: O(N) untuk alokasi array hasil dan kurva-S.
 */
final class WeightedTimePhasing
{
    public function __construct(
        private readonly ?WeightedTimePhasingCertificateVerifier $verifier = null
    ) {}

    /**
     * @param  int  $totalTarget  Total target produksi integer (misalnya ton atau kg)
     * @param  list<int>  $weights  Bobot ketersediaan per periode/hari (harus > 0)
     */
    public function phase(int $totalTarget, array $weights): WeightedTimePhasingResult
    {
        if ($totalTarget < 0) {
            throw new InvalidArgumentException("Total target tidak boleh negatif: {$totalTarget}");
        }

        if ($weights === []) {
            throw new InvalidArgumentException('Daftar bobot tidak boleh kosong');
        }

        $totalWeight = array_sum($weights);
        if ($totalWeight <= 0) {
            throw new InvalidArgumentException('Total bobot harus lebih besar dari nol');
        }

        $n = count($weights);
        $floors = [];
        $remainders = [];
        $exactQuotas = [];
        $allocatedSum = 0;

        for ($i = 0; $i < $n; $i++) {
            $w = $weights[$i];
            if ($w < 0) {
                throw new InvalidArgumentException("Bobot pada indeks {$i} tidak boleh negatif: {$w}");
            }

            $exact = ($totalTarget * $w) / $totalWeight;
            $exactQuotas[] = $exact;

            $floor = (int) floor($exact);
            $floors[$i] = $floor;
            $allocatedSum += $floor;

            // Simpan sisa pecahan untuk Largest Remainder tie-break indeks
            $remainders[] = [
                'index' => $i,
                'rem' => $exact - $floor,
            ];
        }

        $remainderToDistribute = $totalTarget - $allocatedSum;

        // Urutkan sisa pecahan menurun; jika seri, tie-break berdasarkan indeks menaik
        usort($remainders, function (array $a, array $b): int {
            if ($b['rem'] > $a['rem']) {
                return 1;
            }
            if ($b['rem'] < $a['rem']) {
                return -1;
            }

            return $a['index'] <=> $b['index'];
        });

        for ($j = 0; $j < $remainderToDistribute; $j++) {
            $idx = $remainders[$j]['index'];
            $floors[$idx]++;
        }

        // Hitung kurva-S kumulatif
        $cumulative = 0;
        $cumulativeSCurve = [];
        for ($i = 0; $i < $n; $i++) {
            $cumulative += $floors[$i];
            $cumulativeSCurve[] = $cumulative;
        }

        $certificateData = [
            'total_target' => $totalTarget,
            'weights' => $weights,
            'allocations' => $floors,
            'cumulative_s_curve' => $cumulativeSCurve,
            'exact_quotas' => $exactQuotas,
            'algorithm' => 'WeightedTimePhasing_v1',
        ];

        $certificateHash = hash('sha256', json_encode($certificateData, JSON_THROW_ON_ERROR));

        $result = new WeightedTimePhasingResult(
            totalTarget: $totalTarget,
            dailyAllocations: $floors,
            cumulativeSCurve: $cumulativeSCurve,
            exactQuotas: $exactQuotas,
            certificateHash: $certificateHash,
            certificateData: $certificateData
        );

        $verifier = $this->verifier ?? new WeightedTimePhasingCertificateVerifier();
        $verifier->verify($result);

        return $result;
    }
}
