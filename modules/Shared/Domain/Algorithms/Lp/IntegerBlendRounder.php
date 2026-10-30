<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Lp;

use Brick\Math\BigRational;

/**
 * Pembulat solusi kontinu Simplex rasional ke integer kg eksak untuk blending batubara/mineral (PR2, PR4, T-4, B0.8).
 *
 * Menerapkan algoritma perbaikan greedy deterministik:
 * 1. Membulatkan floor tiap variabel kontinu.
 * 2. Memeriksa batasan rasio homogen (Σ a_kj * x_j <= 0).
 * 3. Bila terlanggar, mengurangi 1 kg dari variabel penyumbang pelanggaran terbesar (tie-break indeks menaik).
 * 4. Berhenti paling lambat setelah Σ x langkah.
 * 5. Melaporkan status TIDAK_LAYAK_SETELAH_PEMBULATAN bila total kg < tonase minimum.
 */
final class IntegerBlendRounder
{
    /**
     * @param  list<BigRational>  $xRational  Solusi optimum kontinu dari RationalSimplex
     * @param  int  $minTonnageKg  Batas tonase minimum (kg integer)
     * @param  int  $maxTonnageKg  Batas tonase maksimum (kg integer)
     * @param  list<int>  $stockpileCapsKg  Kapasitas maksimum tiap stockpile (kg integer)
     * @param  list<list<int>>  $ratioConstraints  Matriks batasan homogen (Σ a_kj * x_j <= 0)
     * @return array{status: LpStatus, x: list<int>, totalKg: int}
     */
    public function round(
        array $xRational,
        int $minTonnageKg,
        int $maxTonnageKg,
        array $stockpileCapsKg,
        array $ratioConstraints
    ): array {
        $n = count($xRational);

        // 1. Floor tiap variabel kontinu ke kg integer
        /** @var list<int> $x */
        $x = [];
        foreach ($xRational as $idx => $val) {
            $floor = $val->getNumerator()->quotient($val->getDenominator())->toInt();
            // Jaga tidak melebihi kapasitas stockpile
            $cap = $stockpileCapsKg[$idx] ?? PHP_INT_MAX;
            $x[] = max(0, min($floor, $cap));
        }

        $totalKg = array_sum($x);
        if ($totalKg > $maxTonnageKg) {
            $excess = $totalKg - $maxTonnageKg;
            for ($j = $n - 1; $j >= 0 && $excess > 0; $j--) {
                $dec = min($x[$j], $excess);
                $x[$j] -= $dec;
                $excess -= $dec;
            }
            $totalKg = array_sum($x);
        }

        // 2. Periksa dan perbaiki batasan homogen (Σ a_kj * x_j <= 0)
        while (true) {
            $totalKg = array_sum($x);
            if ($totalKg < $minTonnageKg) {
                return [
                    'status' => LpStatus::INFEASIBLE_AFTER_ROUNDING,
                    'x' => $x,
                    'totalKg' => $totalKg,
                ];
            }

            // Cari batasan yang terlanggar paling parah
            $worstViolation = 0;
            $worstConstraintIdx = -1;

            foreach ($ratioConstraints as $cIdx => $coeffs) {
                $lhs = 0;
                for ($j = 0; $j < $n; $j++) {
                    $lhs += $coeffs[$j] * $x[$j];
                }
                if ($lhs > $worstViolation) {
                    $worstViolation = $lhs;
                    $worstConstraintIdx = $cIdx;
                }
            }

            // Semua batasan homogen terpenuhi (lhs <= 0)
            if ($worstViolation <= 0) {
                return [
                    'status' => LpStatus::OPTIMAL,
                    'x' => $x,
                    'totalKg' => $totalKg,
                ];
            }

            // Cari variabel penyumbang pelanggaran terbesar (a_kj terbesar dengan x_j > 0)
            // Tie-break indeks menaik
            $coeffs = $ratioConstraints[$worstConstraintIdx];
            $bestVarIdx = -1;
            $maxContribution = 0;

            for ($j = 0; $j < $n; $j++) {
                if ($x[$j] > 0 && $coeffs[$j] > $maxContribution) {
                    $maxContribution = $coeffs[$j];
                    $bestVarIdx = $j;
                }
            }

            // Kurangi 1 kg dari variabel terbaik
            $x[$bestVarIdx]--;
        }
    }
}
