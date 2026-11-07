<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

use InvalidArgumentException;

/**
 * H1 Hotel\OverbookingLimitOptimizer [Fase 91]
 *
 * Memilih k (kamar dijual di atas kapasitas fisik) yang memaksimalkan:
 * E[pendapatan] - E[biaya walk]
 * Rumus:
 *   terhuni = min(kapasitas, terjual - no_show)
 *   walk    = max(0, terjual - no_show - kapasitas)
 * Dibatasi kebijakan (fixture 3% kapasitas, pembulatan ke bawah); tie-break k lebih kecil.
 * Kompleksitas: O(k_max * n) dengan n adalah jumlah titik distribusi.
 */
final class OverbookingLimitOptimizer
{
    /**
     * @param  int  $capacity  Kapasitas fisik kamar (mis. 100)
     * @param  list<int>  $noShowDistributionBps  Distribusi probabilitas no-show dalam basis points (sum = 10.000 bps)
     * @param  int  $revenuePerRoomIdr  Pendapatan kamar per malam (IDR)
     * @param  int  $walkCostIdr  Biaya kompensasi & relokasi walk per kamar (IDR)
     * @param  int  $policyMaxPercentBps  Batas kebijakan overbooking dalam bps (default 300 bps = 3%)
     * @return array{
     *     optimal_k: int,
     *     expected_net_value_idr: int,
     *     expected_walk_rooms: float,
     *     values_by_k: list<int>,
     *     policy_ceiling_k: int,
     *     certificate: OverbookingCertificate
     * }
     */
    public static function optimize(
        int $capacity,
        array $noShowDistributionBps,
        int $revenuePerRoomIdr,
        int $walkCostIdr,
        int $policyMaxPercentBps = 300
    ): array {
        if ($capacity <= 0) {
            throw new InvalidArgumentException("Kapasitas kamar harus positif: {$capacity}");
        }
        if ($revenuePerRoomIdr <= 0 || $walkCostIdr <= 0) {
            throw new InvalidArgumentException('Pendapatan kamar dan biaya walk harus positif.');
        }
        $totalBps = array_sum($noShowDistributionBps);
        if ($totalBps !== 10000) {
            throw new InvalidArgumentException("Distribusi no-show harus berjumlah 10.000 bps, ditemukan: {$totalBps}");
        }

        $policyCeilingK = intdiv($capacity * $policyMaxPercentBps, 10000);
        $maxPossibleK = count($noShowDistributionBps) - 1;

        $valuesByK = [];
        $walkRoomsByK = [];

        for ($k = 0; $k <= $maxPossibleK; $k++) {
            $sold = $capacity + $k;
            $expectedNetVal = 0.0;
            $expectedWalk = 0.0;

            foreach ($noShowDistributionBps as $ns => $bps) {
                $p = $bps / 10000.0;
                $occupied = min($capacity, $sold - $ns);
                $walk = max(0, $sold - $ns - $capacity);

                $expectedNetVal += $p * ($occupied * $revenuePerRoomIdr - $walk * $walkCostIdr);
                $expectedWalk += $p * $walk;
            }

            $valuesByK[$k] = (int) round($expectedNetVal);
            $walkRoomsByK[$k] = $expectedWalk;
        }

        // Cari k terbaik dengan batasan kebijakan: k <= policyCeilingK
        // Tie-break k lebih kecil
        $bestK = 0;
        $bestVal = PHP_INT_MIN;

        for ($k = 0; $k <= $policyCeilingK && $k <= $maxPossibleK; $k++) {
            if ($valuesByK[$k] > $bestVal) {
                $bestVal = $valuesByK[$k];
                $bestK = $k;
            }
        }

        $certificate = new OverbookingCertificate(
            capacity: $capacity,
            optimalK: $bestK,
            expectedNetValueIdr: $bestVal,
            expectedWalkRooms: $walkRoomsByK[$bestK],
            policyCeilingK: $policyCeilingK,
            valuesByK: $valuesByK,
            timestamp: time()
        );

        OverbookingCertificateVerifier::verify($certificate);

        return [
            'optimal_k' => $bestK,
            'expected_net_value_idr' => $bestVal,
            'expected_walk_rooms' => $walkRoomsByK[$bestK],
            'values_by_k' => $valuesByK,
            'policy_ceiling_k' => $policyCeilingK,
            'certificate' => $certificate,
        ];
    }
}
