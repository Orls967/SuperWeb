<?php

declare(strict_types=1);

namespace App\Quality\Algorithms;

/**
 * Pembangun Design Structure Matrix (DSM) dependensi modul dan kelas.
 */
final class DependencyMatrixBuilder
{
    /**
     * @param array<string, list<string>> $adjacency
     * @return array{nodes: list<string>, matrix: array<string, array<string, int>>}
     */
    public function buildDsm(array $adjacency): array
    {
        $nodes = array_keys($adjacency);
        sort($nodes);

        $matrix = [];
        foreach ($nodes as $row) {
            $matrix[$row] = [];
            $deps = $adjacency[$row] ?? [];
            foreach ($nodes as $col) {
                $matrix[$row][$col] = in_array($col, $deps, true) ? 1 : 0;
            }
        }

        return [
            'nodes' => $nodes,
            'matrix' => $matrix,
        ];
    }

    /**
     * Menghitung skor keterikatan siklik (cyclic coupling score)
     *
     * @param array<string, array<string, int>> $matrix
     */
    public function calculateCouplingScore(array $matrix): int
    {
        $score = 0;
        $nodes = array_keys($matrix);
        $count = count($nodes);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $u = $nodes[$i];
                $v = $nodes[$j];

                // Jika u -> v dan v -> u, terdeteksi circular dependency
                if (($matrix[$u][$v] ?? 0) === 1 && ($matrix[$v][$u] ?? 0) === 1) {
                    $score += 2;
                }
            }
        }

        return $score;
    }
}

