<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Simulation\Verifiers;

/**
 * Detektor siklus graf dependensi untuk pemesanan topologis Kahn A2.
 * Menggunakan pendekatan DFS coloring (WHITE=0, GRAY=1, BLACK=2) untuk mengisolasi Strongly Connected Components (SCC) siklik.
 */
final class TopologicalGraphCycleDetector
{
    private const WHITE = 0; // Belum dikunjungi

    private const GRAY = 1;  // Sedang dieksplorasi (dalam stack rekursi)

    private const BLACK = 2; // Tuntas dikunjungi

    /**
     * @param  array<string, array<string>>  $adjacencyList  Map: node => array of prerequisites (in-edges)
     * @return array{has_cycle: bool, cycle_path: array<string>}
     */
    public function detectCycle(array $adjacencyList): array
    {
        $allNodes = array_keys($adjacencyList);
        foreach ($adjacencyList as $deps) {
            foreach ($deps as $dep) {
                if (! in_array($dep, $allNodes, true)) {
                    $allNodes[] = $dep;
                }
            }
        }

        $color = [];
        $parent = [];
        foreach ($allNodes as $node) {
            $color[$node] = self::WHITE;
            $parent[$node] = null;
        }

        foreach ($allNodes as $node) {
            if ($color[$node] === self::WHITE) {
                $cycle = $this->dfsVisit($node, $adjacencyList, $color, $parent);
                if ($cycle !== null) {
                    return [
                        'has_cycle' => true,
                        'cycle_path' => $cycle,
                    ];
                }
            }
        }

        return [
            'has_cycle' => false,
            'cycle_path' => [],
        ];
    }

    /**
     * @param  array<string, array<string>>  $adj
     * @param  array<string, int>  $color
     * @param  array<string, string|null>  $parent
     * @return array<string>|null
     */
    private function dfsVisit(string $u, array &$adj, array &$color, array &$parent): ?array
    {
        $color[$u] = self::GRAY;

        $neighbors = $adj[$u] ?? [];
        foreach ($neighbors as $v) {
            if (! isset($color[$v])) {
                $color[$v] = self::WHITE;
                $parent[$v] = null;
            }

            if ($color[$v] === self::GRAY) {
                // Back-edge terdeteksi: siklus ditemukan! Rekonstruksi lintasan.
                $cycle = [$v, $u];
                $curr = $parent[$u] ?? null;
                while ($curr !== null && $curr !== $v) {
                    $cycle[] = $curr;
                    $curr = $parent[$curr] ?? null;
                }
                $cycle[] = $v;

                return array_reverse($cycle);
            }

            if ($color[$v] === self::WHITE) {
                $parent[$v] = $u;
                $cycle = $this->dfsVisit($v, $adj, $color, $parent);
                if ($cycle !== null) {
                    return $cycle;
                }
            }
        }

        $color[$u] = self::BLACK;

        return null;
    }
}
