<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Flow;

use InvalidArgumentException;
use SplQueue;

/**
 * Algoritma Dinic untuk Aliran Maksimum (Max-Flow) dan Potongan Minimum (Min-Cut) (PR2, O-6).
 *
 * Kompleksitas: O(V^2 * E).
 * Menghasilkan nilai aliran maksimum, distribusi aliran per sisi, dan sertifikat min-cut S-T.
 */
final class DinicMaxFlow
{
    public const int INF = 1_000_000_000_000; // 1e12

    /** @var list<array{u: int, v: int, cap: int}> */
    private array $originalEdges = [];

    /** @var array<int, list<array{to: int, rev: int, cap: int, flow: int, origIdx: int}>> */
    private array $adj = [];

    /** @var array<int, int> */
    private array $level = [];

    /** @var array<int, int> */
    private array $ptr = [];

    public function __construct(private readonly int $vertexCount)
    {
        if ($this->vertexCount < 2) {
            throw new InvalidArgumentException("Jumlah simpul minimal 2, diberikan: {$this->vertexCount}");
        }

        for ($i = 0; $i < $this->vertexCount; $i++) {
            $this->adj[$i] = [];
        }
    }

    /**
     * Menambahkan sisi berarah berkapasitas non-negatif.
     * Mengembalikan indeks sisi asli (0-based).
     */
    public function addEdge(int $u, int $v, int $capacity): int
    {
        if ($u < 0 || $u >= $this->vertexCount || $v < 0 || $v >= $this->vertexCount) {
            throw new InvalidArgumentException("Simpul ({$u}, {$v}) di luar rentang [0, {$this->vertexCount})");
        }
        if ($u === $v) {
            throw new InvalidArgumentException("Self-loop ({$u} -> {$v}) tidak diizinkan");
        }
        if ($capacity < 0) {
            throw new InvalidArgumentException("Kapasitas sisi tidak boleh negatif: {$capacity}");
        }

        $origIdx = count($this->originalEdges);
        $this->originalEdges[] = ['u' => $u, 'v' => $v, 'cap' => $capacity];

        $forwardIdx = count($this->adj[$u]);
        $backwardIdx = count($this->adj[$v]);

        $this->adj[$u][] = [
            'to' => $v,
            'rev' => $backwardIdx,
            'cap' => $capacity,
            'flow' => 0,
            'origIdx' => $origIdx,
        ];

        $this->adj[$v][] = [
            'to' => $u,
            'rev' => $forwardIdx,
            'cap' => 0,
            'flow' => 0,
            'origIdx' => -1,
        ];

        return $origIdx;
    }

    /**
     * Menghitung aliran maksimum dari $source ke $sink beserta sertifikat min-cut.
     *
     * @throws InvalidArgumentException
     */
    public function solve(int $source, int $sink, ?FlowCertificateVerifier $verifier = null): FlowResult
    {
        if ($source < 0 || $source >= $this->vertexCount || $sink < 0 || $sink >= $this->vertexCount) {
            throw new InvalidArgumentException("Sumber/muara di luar rentang: source={$source}, sink={$sink}");
        }
        if ($source === $sink) {
            throw new InvalidArgumentException("Sumber dan muara tidak boleh sama ({$source})");
        }

        $maxFlow = 0;

        while ($this->bfs($source, $sink)) {
            $this->ptr = array_fill(0, $this->vertexCount, 0);

            while (($pushed = $this->dfs($source, $sink, self::INF)) > 0) {
                $maxFlow += $pushed;
            }
        }

        // Kumpulkan aliran per sisi asli
        $flowPerEdge = [];
        for ($u = 0; $u < $this->vertexCount; $u++) {
            foreach ($this->adj[$u] as $edge) {
                if ($edge['origIdx'] !== -1) {
                    $flowPerEdge[$edge['origIdx']] = $edge['flow'];
                }
            }
        }

        // Himpunan simpul S pada potongan minimum (semua simpul yang masih dapat dijangkau dari source di sisa graf)
        $sourceCutSet = [];
        $visited = array_fill(0, $this->vertexCount, false);
        $queue = new SplQueue;
        $queue->enqueue($source);
        $visited[$source] = true;

        while (! $queue->isEmpty()) {
            $curr = $queue->dequeue();
            $sourceCutSet[] = $curr;

            foreach ($this->adj[$curr] as $edge) {
                if ($edge['cap'] - $edge['flow'] > 0 && ! $visited[$edge['to']]) {
                    $visited[$edge['to']] = true;
                    $queue->enqueue($edge['to']);
                }
            }
        }

        sort($sourceCutSet);

        $result = new FlowResult(
            maxFlow: $maxFlow,
            flowPerEdge: $flowPerEdge,
            sourceCutSet: $sourceCutSet,
        );

        // Verifikasi sertifikat independen di produksi (PR2)
        $verifier ??= new FlowCertificateVerifier;
        $verifier->verify($this->originalEdges, $this->vertexCount, $source, $sink, $result);

        return $result;
    }

    private function bfs(int $source, int $sink): bool
    {
        $this->level = array_fill(0, $this->vertexCount, -1);
        $this->level[$source] = 0;

        $queue = new SplQueue;
        $queue->enqueue($source);

        while (! $queue->isEmpty()) {
            $u = $queue->dequeue();

            foreach ($this->adj[$u] as $edge) {
                if ($edge['cap'] - $edge['flow'] > 0 && $this->level[$edge['to']] === -1) {
                    $this->level[$edge['to']] = $this->level[$u] + 1;
                    $queue->enqueue($edge['to']);
                }
            }
        }

        return $this->level[$sink] !== -1;
    }

    private function dfs(int $u, int $sink, int $pushed): int
    {
        if ($pushed === 0 || $u === $sink) {
            return $pushed;
        }

        $edgeCount = count($this->adj[$u]);
        for ($cid = $this->ptr[$u]; $cid < $edgeCount; $cid = ++$this->ptr[$u]) {
            $edge = &$this->adj[$u][$cid];
            $to = $edge['to'];

            if ($this->level[$to] !== $this->level[$u] + 1 || $edge['cap'] - $edge['flow'] <= 0) {
                continue;
            }

            $tr = $this->dfs($to, $sink, min($pushed, $edge['cap'] - $edge['flow']));
            if ($tr === 0) {
                continue;
            }

            $edge['flow'] += $tr;
            $this->adj[$to][$edge['rev']]['flow'] -= $tr;

            return $tr;
        }

        return 0;
    }
}
