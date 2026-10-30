<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Lp;

use Brick\Math\BigRational;
use RuntimeException;

/**
 * Verifier sertifikat independen untuk Linear Programming Simplex (PR2).
 *
 * Memverifikasi:
 * 1. Kasus OPTIMAL:
 *    - Kelayakan primal: x* >= 0, A*x* <= b
 *    - Kelayakan dual: y* >= 0, A^T * y* >= c
 *    - Teorema dualitas kuat: c*x* == b*y* == objectiveValue
 * 2. Kasus INFEASIBLE:
 *    - Sertifikat Farkas: y >= 0, A^T * y >= 0, b^T * y < 0 (bukti matematis tidak ada x >= 0 dengan A*x <= b).
 */
class LpCertificateVerifier
{
    /**
     * @param  list<BigRational>  $c  Koefisien fungsi tujuan primal (panjang n)
     * @param  list<list<BigRational>>  $A  Matriks batasan m x n
     * @param  list<BigRational>  $b  Vektor sisi kanan batasan (panjang m)
     * @param  LpResult  $result  Hasil dari RationalSimplex
     *
     * @throws RuntimeException Bila sertifikat tidak valid
     */
    public function verify(array $c, array $A, array $b, LpResult $result): bool
    {
        $m = count($A);
        $n = count($c);

        if ($result->status === LpStatus::OPTIMAL) {
            return $this->verifyOptimal($c, $A, $b, $result, $m, $n);
        }

        if ($result->status === LpStatus::INFEASIBLE) {
            return $this->verifyFarkas($A, $b, $result, $m, $n);
        }

        if ($result->status === LpStatus::UNBOUNDED) {
            return true;
        }

        return false;
    }

    private function verifyOptimal(
        array $c,
        array $A,
        array $b,
        LpResult $result,
        int $m,
        int $n
    ): bool {
        if (count($result->x) !== $n) {
            throw new RuntimeException('Panjang vektor solusi primal x* tidak sama dengan n');
        }
        if (count($result->y) !== $m) {
            throw new RuntimeException('Panjang vektor solusi dual y* tidak sama dengan m');
        }
        if ($result->objectiveValue === null) {
            throw new RuntimeException('Nilai fungsi tujuan tidak boleh null pada solusi optimal');
        }

        // 1. Primal feasibility: x* >= 0
        foreach ($result->x as $j => $xj) {
            if ($xj->isNegative()) {
                throw new RuntimeException("Pelanggaran kelayakan primal: x_{$j} ({$xj}) bernilai negatif");
            }
        }

        // Primal feasibility: A * x* <= b
        for ($i = 0; $i < $m; $i++) {
            $ax = BigRational::zero();
            for ($j = 0; $j < $n; $j++) {
                $ax = $ax->plus($A[$i][$j]->multipliedBy($result->x[$j]));
            }

            if ($ax->isGreaterThan($b[$i])) {
                throw new RuntimeException(
                    "Pelanggaran batasan primal baris {$i}: A*x ({$ax}) > b_{$i} ({$b[$i]})"
                );
            }
        }

        // 2. Dual feasibility: y* >= 0
        foreach ($result->y as $i => $yi) {
            if ($yi->isNegative()) {
                throw new RuntimeException("Pelanggaran kelayakan dual: y_{$i} ({$yi}) bernilai negatif");
            }
        }

        // Dual feasibility: A^T * y* >= c
        for ($j = 0; $j < $n; $j++) {
            $aty = BigRational::zero();
            for ($i = 0; $i < $m; $i++) {
                $aty = $aty->plus($A[$i][$j]->multipliedBy($result->y[$i]));
            }

            if ($aty->isLessThan($c[$j])) {
                throw new RuntimeException(
                    "Pelanggaran batasan dual kolom {$j}: A^T*y ({$aty}) < c_{$j} ({$c[$j]})"
                );
            }
        }

        // 3. Strong duality: c * x* == b * y* == objectiveValue
        $cx = BigRational::zero();
        for ($j = 0; $j < $n; $j++) {
            $cx = $cx->plus($c[$j]->multipliedBy($result->x[$j]));
        }

        $by = BigRational::zero();
        for ($i = 0; $i < $m; $i++) {
            $by = $by->plus($b[$i]->multipliedBy($result->y[$i]));
        }

        if (! $cx->isEqualTo($result->objectiveValue)) {
            throw new RuntimeException(
                "Nilai c*x ({$cx}) tidak sama dengan objectiveValue ({$result->objectiveValue})"
            );
        }

        if (! $cx->isEqualTo($by)) {
            throw new RuntimeException(
                "Pelanggaran dualitas kuat: c*x ({$cx}) != b*y ({$by})"
            );
        }

        return true;
    }

    private function verifyFarkas(
        array $A,
        array $b,
        LpResult $result,
        int $m,
        int $n
    ): bool {
        if (count($result->y) !== $m) {
            throw new RuntimeException('Panjang sertifikat Farkas y tidak sama dengan m');
        }

        // 1. y >= 0
        foreach ($result->y as $i => $yi) {
            if ($yi->isNegative()) {
                throw new RuntimeException("Sertifikat Farkas tidak valid: y_{$i} bernilai negatif ({$yi})");
            }
        }

        // 2. A^T * y >= 0
        for ($j = 0; $j < $n; $j++) {
            $aty = BigRational::zero();
            for ($i = 0; $i < $m; $i++) {
                $aty = $aty->plus($A[$i][$j]->multipliedBy($result->y[$i]));
            }

            if ($aty->isNegative()) {
                throw new RuntimeException(
                    "Sertifikat Farkas tidak valid: A^T*y pada kolom {$j} bernilai negatif ({$aty})"
                );
            }
        }

        // 3. b^T * y < 0
        $by = BigRational::zero();
        for ($i = 0; $i < $m; $i++) {
            $by = $by->plus($b[$i]->multipliedBy($result->y[$i]));
        }

        if (! $by->isNegative()) {
            throw new RuntimeException(
                "Sertifikat Farkas tidak valid: b^T*y ({$by}) tidak kurang dari 0"
            );
        }

        return true;
    }
}
