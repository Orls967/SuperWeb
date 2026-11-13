<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Algorithms;

use Modules\Wms\Domain\Certificates\TrafficDeadlockCertificate;

/**
 * Detektor siklus wait-for graph deterministik untuk mencegah gridlock antar misi/robot (X279-3).
 *
 * Kompleksitas: O(V log V + V + E) dengan V = jumlah simpul aktif dan E = jumlah edge tunggu.
 * Aritmetika: bilangan bulat untuk prioritas; tie-break kode simpul menaik secara leksikografis.
 */
final class TrafficDeadlockDetector
{
    /**
     * @param  array<string, string>  $waitForEdges  Peta node_code => target_node_code yang ditunggu
     * @return array{has_deadlock: bool, cycle_nodes: list<string>}
     */
    public function detectCycle(array $waitForEdges): array
    {
        ksort($waitForEdges);
        $state = []; // 0/unset = unvisited, 1 = visiting, 2 = visited

        foreach (array_keys($waitForEdges) as $startNode) {
            if (($state[$startNode] ?? 0) === 2) {
                continue;
            }

            $current = $startNode;
            $path = [];

            while ($current !== null) {
                if (($state[$current] ?? 0) === 2) {
                    break;
                }

                if (($state[$current] ?? 0) === 1) {
                    $cycleStartIdx = array_search($current, $path, true);
                    $cycleNodes = $cycleStartIdx !== false
                        ? array_values(array_slice($path, $cycleStartIdx))
                        : [$current];

                    return [
                        'has_deadlock' => true,
                        'cycle_nodes' => $cycleNodes,
                    ];
                }

                $state[$current] = 1;
                $path[] = $current;
                $current = $waitForEdges[$current] ?? null;
            }

            foreach ($path as $node) {
                $state[$node] = 2;
            }
        }

        return [
            'has_deadlock' => false,
            'cycle_nodes' => [],
        ];
    }

    /**
     * Deteksi siklus dan pilih misi berprioritas terendah untuk dibatalkan (kompensasi pelepasan reservasi).
     *
     * @param  array<string, string>  $waitForEdges
     * @param  array<string, int>  $nodePriorities  Semakin besar angka = prioritas lebih tinggi; terkecil = korban
     * @return array{
     *     has_deadlock: bool,
     *     cycle_nodes: list<string>,
     *     victim_node: string|null,
     *     victim_priority: int|null,
     *     certificate: TrafficDeadlockCertificate
     * }
     */
    public function detectAndResolve(array $waitForEdges, array $nodePriorities = []): array
    {
        ksort($waitForEdges);
        ksort($nodePriorities);

        $cycle = $this->detectCycle($waitForEdges);
        $victim = null;
        $victimPriority = null;
        $steps = [];

        if ($cycle['has_deadlock']) {
            $candidates = $cycle['cycle_nodes'];
            usort($candidates, static function (string $a, string $b) use ($nodePriorities): int {
                $pa = $nodePriorities[$a] ?? 100;
                $pb = $nodePriorities[$b] ?? 100;
                if ($pa !== $pb) {
                    return $pa <=> $pb;
                }

                return $a <=> $b;
            });

            $victim = $candidates[0] ?? null;
            $victimPriority = $victim !== null ? ($nodePriorities[$victim] ?? 100) : null;
            $steps[] = [
                'cycle_nodes' => $cycle['cycle_nodes'],
                'selected_victim' => $victim,
                'selected_priority' => $victimPriority,
            ];
        } else {
            $steps[] = ['status' => 'NO_CYCLE'];
        }

        $result = [
            'has_deadlock' => $cycle['has_deadlock'],
            'cycle_nodes' => $cycle['cycle_nodes'],
            'victim_node' => $victim,
            'victim_priority' => $victimPriority,
        ];

        $certificate = TrafficDeadlockCertificate::issue(
            ['edges' => $waitForEdges, 'priorities' => $nodePriorities],
            $steps,
            $result,
        );
        $certificate->verifyOrFail();

        return [
            ...$result,
            'certificate' => $certificate,
        ];
    }
}
