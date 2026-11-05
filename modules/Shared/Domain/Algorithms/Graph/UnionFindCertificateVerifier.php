<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Graph;

use InvalidArgumentException;

final class UnionFindCertificateVerifier
{
    /**
     * Memverifikasi keabsahan sertifikat partisi komponen UnionFind (A6).
     *
     * Invarian yang diverifikasi:
     * 1. Seluruh elemen input terpartisi secara unik ke dalam komponen (disjoint and exhaustive).
     * 2. Setiap edge [u, v] wajib berada di dalam komponen yang sama (representative identik).
     * 3. Jumlah komponen cocok dengan total key pada peta komponen.
     * 4. Setiap root adalah representasi sah (parent[root] === root).
     */
    public function verify(UnionFindResult $result): bool
    {
        // 1. Validasi representatif dan parent mapping
        foreach ($result->parents as $node => $parent) {
            if (! in_array($node, $result->elements, true)) {
                throw new InvalidArgumentException("Node '{$node}' di tabel parent tidak terdaftar di elemen input.");
            }
            if (! in_array($parent, $result->elements, true)) {
                throw new InvalidArgumentException("Parent '{$parent}' dari node '{$node}' tidak terdaftar di elemen input.");
            }
        }

        // 2. Validasi partisi elemen
        $allMembers = [];
        foreach ($result->components as $root => $members) {
            if (($result->parents[$root] ?? null) !== $root) {
                throw new InvalidArgumentException("Representative '{$root}' bukan root sejati (parent tidak mengarah ke diri sendiri).");
            }
            foreach ($members as $member) {
                if (isset($allMembers[$member])) {
                    throw new InvalidArgumentException("Elemen '{$member}' muncul di lebih dari satu komponen.");
                }
                $allMembers[$member] = $root;
            }
        }

        if (count($allMembers) !== count($result->elements)) {
            throw new InvalidArgumentException(sprintf(
                'Jumlah elemen terpartisi (%d) tidak sama dengan jumlah elemen input (%d).',
                count($allMembers),
                count($result->elements)
            ));
        }

        // 3. Validasi edge: kedua ujung edge wajib memiliki root yang sama
        foreach ($result->edges as [$u, $v]) {
            $rootU = $allMembers[$u] ?? null;
            $rootV = $allMembers[$v] ?? null;

            if ($rootU === null || $rootV === null) {
                throw new InvalidArgumentException("Edge [{$u}, {$v}] menghubungkan node yang tidak terpartisi.");
            }

            if ($rootU !== $rootV) {
                throw new InvalidArgumentException("Edge [{$u}, {$v}] menghubungkan komponen yang berbeda ({$rootU} vs {$rootV}).");
            }
        }

        // 4. Validasi jumlah komponen
        if (count($result->components) !== $result->componentCount) {
            throw new InvalidArgumentException(sprintf(
                'Jumlah komponen (%d) tidak cocok dengan componentCount (%d).',
                count($result->components),
                $result->componentCount
            ));
        }

        return true;
    }
}
