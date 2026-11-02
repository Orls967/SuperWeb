<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Assignment;

use RuntimeException;

/**
 * Verifier sertifikat dual untuk solusi penugasan Hungarian (PR2).
 * Memeriksa independen dari solver:
 * 1. u_i + v_j <= c_ij untuk semua sel terhingga.
 * 2. Kesetaraan persis u_i + v_j == c_ij pada sel yang ditugaskan (complementary slackness).
 * 3. Σ biaya penugasan == Σ u_i + Σ v_j (dualitas kuat).
 */
class AssignmentCertificateVerifier
{
    /**
     * @param  list<list<int>>  $costMatrix  Matriks biaya integer n x m
     * @param  AssignmentResult  $result  Hasil dari solver
     *
     * @throws RuntimeException Bila sertifikat tidak valid
     */
    public function verify(array $costMatrix, AssignmentResult $result, int $infCost = HungarianSolver::INF): bool
    {
        $n = count($costMatrix);
        if ($n === 0) {
            return true;
        }
        $m = count($costMatrix[0]);

        if (count($result->u) !== $n) {
            throw new RuntimeException('Panjang potensial dual u ('.count($result->u).") tidak cocok dengan baris ({$n})");
        }
        if (count($result->v) !== $m) {
            throw new RuntimeException('Panjang potensial dual v ('.count($result->v).") tidak cocok dengan kolom ({$m})");
        }

        // 1. Dual feasibility: u_i + v_j <= c_ij untuk semua sel terhingga
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $m; $j++) {
                $c = $costMatrix[$i][$j];
                if ($c >= $infCost) {
                    continue;
                }

                if ($c < $result->u[$i] + $result->v[$j]) {
                    throw new RuntimeException(
                        "Pelanggaran kelayakan dual pada sel ({$i}, {$j}): u_{$i} ({$result->u[$i]}) + v_{$j} ({$result->v[$j]}) = "
                        .($result->u[$i] + $result->v[$j])." > c_{$i}{$j} ({$c})"
                    );
                }
            }
        }

        // 2. Complementary slackness: u_i + v_j == c_ij pada sel terpilih
        $assignedCostSum = 0;
        $usedCols = [];
        for ($i = 0; $i < $n; $i++) {
            if (! isset($result->assignment[$i])) {
                throw new RuntimeException("Baris {$i} tidak memiliki penugasan kolom");
            }

            $j = $result->assignment[$i];
            if ($j < 0 || $j >= $m) {
                throw new RuntimeException("Penugasan kolom {$j} untuk baris {$i} di luar rentang [0, {$m})");
            }
            if (isset($usedCols[$j])) {
                throw new RuntimeException("Kolom {$j} ditugaskan lebih dari satu kali (baris {$usedCols[$j]} dan {$i})");
            }
            $usedCols[$j] = $i;

            $c = $costMatrix[$i][$j];
            if ($c >= $infCost) {
                throw new RuntimeException("Baris {$i} ditugaskan ke kolom tak layak {$j} dengan biaya tak terhingga");
            }

            if ($c !== $result->u[$i] + $result->v[$j]) {
                throw new RuntimeException(
                    "Pelanggaran complementary slackness pada penugasan ({$i}, {$j}): "
                    ."u_{$i} ({$result->u[$i]}) + v_{$j} ({$result->v[$j]}) != c_{$i}{$j} ({$c})"
                );
            }

            $assignedCostSum += $c;
        }

        if ($assignedCostSum !== $result->totalCost) {
            throw new RuntimeException("Total biaya penugasan ({$assignedCostSum}) != result->totalCost ({$result->totalCost})");
        }

        // 3. Strong duality: Σ biaya == Σ u_i + Σ v_{assigned} (dan v unassigned <= 0)
        $dualSum = array_sum($result->u);
        foreach ($usedCols as $j => $i) {
            $dualSum += $result->v[$j];
        }

        // Kolom yang tidak terpakai harus memiliki v_j <= 0
        for ($j = 0; $j < $m; $j++) {
            if (! isset($usedCols[$j]) && $result->v[$j] > 0) {
                throw new RuntimeException("Kolom tidak terpakai {$j} memiliki potensial v_j > 0 ({$result->v[$j]})");
            }
        }

        if ($assignedCostSum !== $dualSum) {
            throw new RuntimeException("Pelanggaran dualitas kuat: biaya prima ({$assignedCostSum}) != biaya dual ({$dualSum})");
        }

        return true;
    }
}
