<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Flow;

use RuntimeException;

/**
 * Verifier independen untuk sertifikat Min Cost Flow (PR2).
 *
 * Memverifikasi 5 kondisi matematis:
 * 1. Batas kapasitas: 0 <= f(e) <= c(e) untuk setiap sisi asli.
 * 2. Konservasi aliran di setiap simpul selain sumber (source) dan muara (sink).
 * 3. Total aliran bersih keluar dari sumber == totalFlow.
 * 4. Konsistensi biaya total: sum f(e) * cost(e) == totalCost.
 * 5. Kondisi dualitas Complementary Slackness (reduced cost >= 0 pada seluruh busur residual):
 *    - Untuk sisi maju dengan sisa kapasitas (c - f > 0): cost + pi(u) - pi(v) >= 0.
 *    - Untuk sisi mundur dengan aliran (f > 0): -cost + pi(v) - pi(u) >= 0.
 */
class MinCostFlowCertificateVerifier
{
    /**
     * @param list<array{u: int, v: int, cap: int, cost: int}> $edges Sisi asli graf
     * @param int $vertexCount Jumlah total simpul
     * @param int $source Simpul sumber
     * @param int $sink Simpul muara
     * @param MinCostFlowResult $result Hasil komputasi yang diverifikasi
     *
     * @throws RuntimeException Bila sertifikat tidak valid
     */
    public function verify(
        array $edges,
        int $vertexCount,
        int $source,
        int $sink,
        MinCostFlowResult $result
    ): bool {
        $flowPerEdge = $result->flowPerEdge;
        $potentials = $result->nodePotentials;

        // Inisialisasi neraca aliran per simpul
        $netFlow = array_fill(0, $vertexCount, 0);
        $computedCost = 0;

        foreach ($edges as $idx => $edge) {
            $u = $edge['u'];
            $v = $edge['v'];
            $cap = $edge['cap'];
            $cost = $edge['cost'];
            $flow = $flowPerEdge[$idx] ?? 0;

            // 1. Batas kapasitas
            if ($flow < 0 || $flow > $cap) {
                throw new RuntimeException(
                    "Sisi {$idx} ({$u} -> {$v}) melanggar batas kapasitas: aliran {$flow}, kapasitas {$cap}"
                );
            }

            $netFlow[$u] -= $flow;
            $netFlow[$v] += $flow;
            $computedCost += $flow * $cost;

            $piU = $potentials[$u] ?? 0;
            $piV = $potentials[$v] ?? 0;

            // 5a. Kondisi reduced cost sisi maju dengan residual capacity > 0
            if ($flow < $cap) {
                $reducedCostForward = $cost + $piU - $piV;
                if ($reducedCostForward < 0) {
                    throw new RuntimeException(
                        "Reduced cost sisi maju {$idx} ({$u} -> {$v}) bernilai negatif ({$reducedCostForward})"
                    );
                }
            }

            // 5b. Kondisi reduced cost sisi mundur dengan residual flow > 0
            if ($flow > 0) {
                $reducedCostBackward = -$cost + $piV - $piU;
                if ($reducedCostBackward < 0) {
                    throw new RuntimeException(
                        "Reduced cost sisi mundur {$idx} ({$v} -> {$u}) bernilai negatif ({$reducedCostBackward})"
                    );
                }
            }
        }

        // 2. Konservasi aliran
        for ($i = 0; $i < $vertexCount; $i++) {
            if ($i === $source || $i === $sink) {
                continue;
            }
            if ($netFlow[$i] !== 0) {
                throw new RuntimeException(
                    "Simpul {$i} melanggar konservasi aliran: neraca bersih {$netFlow[$i]}"
                );
            }
        }

        // 3. Aliran total
        if ($netFlow[$source] !== -$result->totalFlow) {
            throw new RuntimeException(
                "Aliran keluar dari source ({$netFlow[$source]}) tidak sama dengan totalFlow ({$result->totalFlow})"
            );
        }

        if ($netFlow[$sink] !== $result->totalFlow) {
            throw new RuntimeException(
                "Aliran masuk ke sink ({$netFlow[$sink]}) tidak sama dengan totalFlow ({$result->totalFlow})"
            );
        }

        // 4. Biaya total
        if ($computedCost !== $result->totalCost) {
            throw new RuntimeException(
                "Perhitungan biaya total ({$computedCost}) berbeda dengan totalCost yang dilaporkan ({$result->totalCost})"
            );
        }

        return true;
    }
}

