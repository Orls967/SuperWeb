<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Flow;

use SplPriorityQueue;

/**
 * Algoritma Min Cost Flow berbasis Successive Shortest Path dengan potensial Johnson.
 * Kompleksitas: O(F * E log V).
 *
 * Mampu menangani:
 * - Kapasitas dan biaya integer.
 * - Reduced costs non-negatif yang dipertahankan melalui pembaruan potensial pi.
 * - Inisialisasi Bellman-Ford bila terdapat sisi dengan biaya negatif.
 * - Verifikasi dual sertifikat via MinCostFlowCertificateVerifier.
 */
class MinCostFlow
{
    /**
     * @var list<array{u: int, v: int, cap: int, cost: int}>
     */
    protected array $edges = [];

    /**
     * @var array<int, list<array{to: int, rev: int, cap: int, flow: int, cost: int, edge_index: int}>>
     */
    protected array $adj = [];

    protected int $vertexCount = 0;

    public function __construct(int $vertexCount = 0)
    {
        $this->vertexCount = $vertexCount;
        $this->adj = array_fill(0, $vertexCount, []);
    }

    /**
     * @return list<array{u: int, v: int, cap: int, cost: int}>
     */
    public function getEdges(): array
    {
        return $this->edges;
    }

    public function getVertexCount(): int
    {
        return $this->vertexCount;
    }

    /**
     * Tambahkan sisi berarah ke dalam jaringan.
     *
     * @return int Indeks sisi asli
     */
    public function addEdge(int $u, int $v, int $cap, int $cost): int
    {
        $maxV = max($u, $v) + 1;
        if ($maxV > $this->vertexCount) {
            for ($i = $this->vertexCount; $i < $maxV; $i++) {
                $this->adj[$i] = [];
            }
            $this->vertexCount = $maxV;
        }

        $edgeIdx = count($this->edges);
        $this->edges[] = ['u' => $u, 'v' => $v, 'cap' => $cap, 'cost' => $cost];

        $forwardIdx = count($this->adj[$u]);
        $backwardIdx = count($this->adj[$v]);

        $this->adj[$u][] = [
            'to' => $v,
            'rev' => $backwardIdx,
            'cap' => $cap,
            'flow' => 0,
            'cost' => $cost,
            'edge_index' => $edgeIdx,
        ];

        $this->adj[$v][] = [
            'to' => $u,
            'rev' => $forwardIdx,
            'cap' => 0,
            'flow' => 0,
            'cost' => -$cost,
            'edge_index' => -1, // Sisi residual mundur
        ];

        return $edgeIdx;
    }

    /**
     * Selesaikan Min Cost Flow dari sumber $source ke muara $sink.
     */
    public function solve(int $source, int $sink, ?int $flowLimit = null): MinCostFlowResult
    {
        $n = $this->vertexCount;
        if ($source === $sink || $flowLimit === 0) {
            return new MinCostFlowResult(0, 0, array_fill(0, count($this->edges), 0), array_fill(0, $n, 0));
        }

        $potentials = array_fill(0, $n, 0);

        // Periksa apakah terdapat biaya negatif pada sisi awal
        $hasNegativeCost = false;
        foreach ($this->edges as $e) {
            if ($e['cost'] < 0) {
                $hasNegativeCost = true;
                break;
            }
        }

        if ($hasNegativeCost) {
            // Inisialisasi potensial dengan Bellman-Ford
            $dist = array_fill(0, $n, 0);
            for ($i = 0; $i < $n - 1; $i++) {
                $updated = false;
                foreach ($this->edges as $e) {
                    if ($e['cap'] > 0 && $dist[$e['u']] + $e['cost'] < $dist[$e['v']]) {
                        $dist[$e['v']] = $dist[$e['u']] + $e['cost'];
                        $updated = true;
                    }
                }
                if (! $updated) {
                    break;
                }
            }
            $potentials = $dist;
        }

        $totalFlow = 0;
        $totalCost = 0;

        while ($flowLimit === null || $totalFlow < $flowLimit) {
            // Dijkstra menggunakan reduced cost: c_pi = cost + pi[u] - pi[v] >= 0
            $dist = array_fill(0, $n, PHP_INT_MAX);
            $parent = array_fill(0, $n, ['u' => -1, 'edge_idx' => -1]);
            $dist[$source] = 0;

            // Priority queue min-heap deterministik: simpan simpul dengan prioritas [dist, u]
            $pq = new class extends SplPriorityQueue {
                public function compare(mixed $a, mixed $b): int
                {
                    // Nilai jarak terkecil diprioritaskan
                    if ($a[0] !== $b[0]) {
                        return $a[0] < $b[0] ? 1 : -1;
                    }
                    // Tie-break indeks simpul lebih kecil diprioritaskan
                    return $a[1] < $b[1] ? 1 : -1;
                }
            };

            $pq->insert($source, [0, $source]);

            while (! $pq->isEmpty()) {
                $u = $pq->extract();

                foreach ($this->adj[$u] as $edgeIdxInAdj => $edge) {
                    if ($edge['cap'] > 0) {
                        $v = $edge['to'];
                        $reducedCost = $edge['cost'] + $potentials[$u] - $potentials[$v];
                        if ($dist[$u] !== PHP_INT_MAX && $dist[$u] + $reducedCost < $dist[$v]) {
                            $dist[$v] = $dist[$u] + $reducedCost;
                            $parent[$v] = ['u' => $u, 'edge_idx' => $edgeIdxInAdj];
                            $pq->insert($v, [$dist[$v], $v]);
                        }
                    }
                }
            }

            // Bila sink tidak dapat dijangkau di graf residual, iterasi selesai
            if ($dist[$sink] === PHP_INT_MAX) {
                break;
            }

            // Perbarui potensial untuk menjaga reduced cost >= 0
            for ($v = 0; $v < $n; $v++) {
                if ($dist[$v] !== PHP_INT_MAX) {
                    $potentials[$v] += $dist[$v];
                } else {
                    $potentials[$v] += $dist[$sink];
                }
            }

            // Hitung kapasitas bottleneck sepanjang jalur penambah
            $pushFlow = ($flowLimit !== null) ? ($flowLimit - $totalFlow) : PHP_INT_MAX;
            $curr = $sink;
            while ($curr !== $source) {
                $p = $parent[$curr];
                $u = $p['u'];
                $idx = $p['edge_idx'];
                $pushFlow = min($pushFlow, $this->adj[$u][$idx]['cap']);
                $curr = $u;
            }

            if ($pushFlow <= 0) {
                break;
            }

            // Tambahkan aliran
            $curr = $sink;
            while ($curr !== $source) {
                $p = $parent[$curr];
                $u = $p['u'];
                $idx = $p['edge_idx'];
                $revIdx = $this->adj[$u][$idx]['rev'];

                $this->adj[$u][$idx]['cap'] -= $pushFlow;
                $this->adj[$u][$idx]['flow'] += $pushFlow;
                $this->adj[$curr][$revIdx]['cap'] += $pushFlow;
                $this->adj[$curr][$revIdx]['flow'] -= $pushFlow;

                $totalCost += $pushFlow * $this->adj[$u][$idx]['cost'];
                $curr = $u;
            }

            $totalFlow += $pushFlow;
        }

        // Susun peta aliran per sisi asli
        $flowPerEdge = [];
        for ($u = 0; $u < $n; $u++) {
            foreach ($this->adj[$u] as $e) {
                if ($e['edge_index'] >= 0) {
                    $flowPerEdge[$e['edge_index']] = $e['flow'];
                }
            }
        }

        return new MinCostFlowResult(
            totalFlow: $totalFlow,
            totalCost: $totalCost,
            flowPerEdge: $flowPerEdge,
            nodePotentials: $potentials,
        );
    }

    /**
     * Helper alokasi tonase dari sumber ke pabrik daur ulang berkapasitas (D79-5).
     *
     * @param array<string, int> $sources [nama_sumber => pasokan_ton]
     * @param array<string, int> $plants [nama_pabrik => kapasitas_ton]
     * @param array<string, array<string, int>> $costs [nama_sumber => [nama_pabrik => biaya_per_ton]]
     * @return array{
     *     allocations: array<string, array<string, int>>,
     *     unallocated: array<string, int>,
     *     total_cost: int,
     *     total_allocated: int,
     *     certificate_valid: bool
     * }
     */
    public static function allocate(array $sources, array $plants, array $costs): array
    {
        $sourceNames = array_keys($sources);
        $plantNames = array_keys($plants);
        sort($sourceNames);
        sort($plantNames);

        $numSources = count($sourceNames);
        $numPlants = count($plantNames);

        $sourceNode = 0;
        $sinkNode = $numSources + $numPlants + 1;
        $mcf = new self($sinkNode + 1);

        // Indeks simpul
        $sourceIndexMap = [];
        $plantIndexMap = [];

        foreach ($sourceNames as $i => $name) {
            $nodeIdx = $i + 1;
            $sourceIndexMap[$name] = $nodeIdx;
            // Super-source -> Sumber (biaya 0, kapasitas pasokan)
            $mcf->addEdge($sourceNode, $nodeIdx, $sources[$name], 0);
        }

        foreach ($plantNames as $j => $name) {
            $nodeIdx = $numSources + 1 + $j;
            $plantIndexMap[$name] = $nodeIdx;
            // Pabrik -> Super-sink (biaya 0, kapasitas pabrik)
            $mcf->addEdge($nodeIdx, $sinkNode, $plants[$name], 0);
        }

        // Sisi antar Sumber -> Pabrik
        $laneEdgeMap = [];
        foreach ($sourceNames as $sName) {
            foreach ($plantNames as $pName) {
                $cost = $costs[$sName][$pName] ?? PHP_INT_MAX;
                if ($cost !== PHP_INT_MAX) {
                    $u = $sourceIndexMap[$sName];
                    $v = $plantIndexMap[$pName];
                    // Kapasitas busur antar sumber dan pabrik adalah sebesar pasokan sumber
                    $eIdx = $mcf->addEdge($u, $v, $sources[$sName], $cost);
                    $laneEdgeMap[$sName][$pName] = $eIdx;
                }
            }
        }

        $result = $mcf->solve($sourceNode, $sinkNode);

        // Verifikasi sertifikat
        $verifier = new MinCostFlowCertificateVerifier;
        $certValid = $verifier->verify(
            $mcf->edges,
            $mcf->vertexCount,
            $sourceNode,
            $sinkNode,
            $result
        );

        $allocations = [];
        $unallocated = [];

        foreach ($sourceNames as $sName) {
            $allocatedFromSource = 0;
            $allocations[$sName] = [];
            foreach ($plantNames as $pName) {
                $eIdx = $laneEdgeMap[$sName][$pName] ?? null;
                $flow = ($eIdx !== null) ? $result->flowPerEdge[$eIdx] : 0;
                $allocations[$sName][$pName] = $flow;
                $allocatedFromSource += $flow;
            }
            $unallocated[$sName] = $sources[$sName] - $allocatedFromSource;
        }

        return [
            'allocations' => $allocations,
            'unallocated' => $unallocated,
            'total_cost' => $result->totalCost,
            'total_allocated' => $result->totalFlow,
            'certificate_valid' => $certValid,
        ];
    }
}
