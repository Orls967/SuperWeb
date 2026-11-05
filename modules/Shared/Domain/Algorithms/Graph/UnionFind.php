<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Graph;

use InvalidArgumentException;

/**
 * Algoritma Bersertifikat A6: Disjoint Set Union (UnionFind) dengan Path Compression & Union by Rank.
 * Digunakan untuk mendeteksi komponen terhubung pada analisis jaringan fraud (fraud ring) klaim asuransi.
 */
final class UnionFind
{
    /** @var array<string, string> */
    private array $parent = [];

    /** @var array<string, int> */
    private array $rank = [];

    /**
     * Jalankan algoritma Union-Find atas himpunan elemen dan daftar sisi (edges).
     *
     * @param  list<string>  $elements
     * @param  list<array{0: string, 1: string}>  $edges
     */
    public function build(array $elements, array $edges): UnionFindResult
    {
        $this->parent = [];
        $this->rank = [];

        $uniqueElements = array_values(array_unique($elements));
        foreach ($uniqueElements as $el) {
            $this->parent[$el] = $el;
            $this->rank[$el] = 0;
        }

        foreach ($edges as [$u, $v]) {
            if (! isset($this->parent[$u])) {
                throw new InvalidArgumentException("Node {$u} pada edge tidak ada dalam daftar elemen.");
            }
            if (! isset($this->parent[$v])) {
                throw new InvalidArgumentException("Node {$v} pada edge tidak ada dalam daftar elemen.");
            }
            $this->union($u, $v);
        }

        // Kompres seluruh path agar parent[node] langsung mengarah ke root sejati
        $components = [];
        foreach ($uniqueElements as $el) {
            $root = $this->find($el);
            $components[$root][] = $el;
        }

        // Urutkan anggota per komponen untuk determinisme
        foreach ($components as $root => $members) {
            sort($members);
            $components[$root] = $members;
        }
        ksort($components);

        $maxSize = 0;
        foreach ($components as $members) {
            $maxSize = max($maxSize, count($members));
        }

        // Tabel parent final terkompresi penuh
        $finalParents = [];
        foreach ($uniqueElements as $el) {
            $finalParents[$el] = $this->find($el);
        }

        return new UnionFindResult(
            elements: $uniqueElements,
            edges: $edges,
            parents: $finalParents,
            ranks: $this->rank,
            components: $components,
            componentCount: count($components),
            maxComponentSize: $maxSize,
        );
    }

    /**
     * Find dengan path compression.
     */
    public function find(string $x): string
    {
        if (! isset($this->parent[$x])) {
            throw new InvalidArgumentException("Elemen '{$x}' tidak terdaftar.");
        }

        if ($this->parent[$x] !== $x) {
            $this->parent[$x] = $this->find($this->parent[$x]);
        }

        return $this->parent[$x];
    }

    /**
     * Union by rank.
     */
    public function union(string $x, string $y): bool
    {
        $rootX = $this->find($x);
        $rootY = $this->find($y);

        if ($rootX === $rootY) {
            return false;
        }

        if ($this->rank[$rootX] < $this->rank[$rootY]) {
            $this->parent[$rootX] = $rootY;
        } elseif ($this->rank[$rootX] > $this->rank[$rootY]) {
            $this->parent[$rootY] = $rootX;
        } else {
            // Tie break deterministik berdasarkan urutan leksikografis
            if (strcmp($rootX, $rootY) < 0) {
                $this->parent[$rootY] = $rootX;
                $this->rank[$rootX]++;
            } else {
                $this->parent[$rootX] = $rootY;
                $this->rank[$rootY]++;
            }
        }

        return true;
    }
}
