<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Assignment;

use InvalidArgumentException;

/**
 * Pemecah masalah penugasan linear (Hungarian / Kuhn-Munkres Algorithm) dengan sertifikat dual (u, v).
 *
 * Mendukung matriks persegi panjang di mana jumlah baris (pekerja) <= jumlah kolom (tugas).
 * Biaya dinyatakan dalam integer. Nilai HungarianSolver::INF merepresentasikan sel yang dilarang.
 *
 * Kompleksitas: O(N^2 * M) di mana N = jumlah baris, M = jumlah kolom.
 * Tie-breaking deterministik: selalu memilih indeks kolom terkecil saat nilai seri.
 */
final class HungarianSolver
{
    public const int INF = 1_000_000_000;

    /**
     * Menyelesaikan penugasan optimal berbiaya minimum.
     *
     * @param  list<list<int>>  $costMatrix  Matriks n x m (n <= m)
     *
     * @throws InvalidArgumentException Bila n > m atau tidak ada penugasan yang layak
     */
    public function solve(array $costMatrix, ?AssignmentCertificateVerifier $verifier = null): AssignmentResult
    {
        $n = count($costMatrix);
        if ($n === 0) {
            return new AssignmentResult([], 0, [], []);
        }

        $m = count($costMatrix[0]);
        if ($n > $m) {
            throw new InvalidArgumentException("Jumlah baris ({$n}) tidak boleh melebihi jumlah kolom ({$m})");
        }

        // Validasi setiap baris
        foreach ($costMatrix as $i => $row) {
            if (count($row) !== $m) {
                throw new InvalidArgumentException("Semua baris matriks harus memiliki panjang kolom yang sama ({$m})");
            }
            if (min($row) >= self::INF) {
                throw new InvalidArgumentException("Baris {$i} tidak memiliki sel terhingga (tidak layak)");
            }
        }

        // Kuhn-Munkres 1-based indexing
        $u = array_fill(0, $n + 1, 0);
        $v = array_fill(0, $m + 1, 0);
        $p = array_fill(0, $m + 1, 0);
        $way = array_fill(0, $m + 1, 0);

        for ($i = 1; $i <= $n; $i++) {
            $p[0] = $i;
            $j0 = 0;
            $minv = array_fill(0, $m + 1, self::INF);
            $used = array_fill(0, $m + 1, false);

            do {
                $used[$j0] = true;
                $i0 = $p[$j0];
                $delta = self::INF;
                $j1 = 0;

                for ($j = 1; $j <= $m; $j++) {
                    if (! $used[$j]) {
                        $cost = $costMatrix[$i0 - 1][$j - 1];
                        $cur = $cost - $u[$i0] - $v[$j];

                        if ($cur < $minv[$j]) {
                            $minv[$j] = $cur;
                            $way[$j] = $j0;
                        }

                        if ($minv[$j] < $delta) {
                            $delta = $minv[$j];
                            $j1 = $j;
                        }
                    }
                }

                if ($delta >= self::INF) {
                    throw new InvalidArgumentException('Masalah penugasan tidak layak: tidak ditemukan jalur penugasan');
                }

                for ($j = 0; $j <= $m; $j++) {
                    if ($used[$j]) {
                        $u[$p[$j]] += $delta;
                        $v[$j] -= $delta;
                    } else {
                        $minv[$j] -= $delta;
                    }
                }

                $j0 = $j1;
            } while ($p[$j0] !== 0);

            // Rekonstruksi jalur augmentasi
            do {
                $j1 = $way[$j0];
                $p[$j0] = $p[$j1];
                $j0 = $j1;
            } while ($j0 !== 0);
        }

        $assignment = [];
        $totalCost = 0;

        for ($j = 1; $j <= $m; $j++) {
            if ($p[$j] > 0) {
                $row = $p[$j] - 1;
                $col = $j - 1;
                if ($costMatrix[$row][$col] >= self::INF) {
                    throw new InvalidArgumentException('Masalah penugasan tidak layak: penugasan melibatkan biaya tak terhingga');
                }
                $assignment[$row] = $col;
                $totalCost += $costMatrix[$row][$col];
            }
        }

        ksort($assignment);

        $rowPotentials = [];
        for ($i = 1; $i <= $n; $i++) {
            $rowPotentials[] = $u[$i];
        }

        $colPotentials = [];
        for ($j = 1; $j <= $m; $j++) {
            $colPotentials[] = $v[$j];
        }

        $result = new AssignmentResult(
            assignment: $assignment,
            totalCost: $totalCost,
            u: $rowPotentials,
            v: $colPotentials,
        );

        // Verifikasi sertifikat independen di produksi (PR2)
        $verifier ??= new AssignmentCertificateVerifier;
        $verifier->verify($costMatrix, $result, self::INF);

        return $result;
    }
}
