<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Hash;

use InvalidArgumentException;

/**
 * Algoritma Kriptografis Merkle Tree deterministik (D86-4).
 * Daun = sha256(leaf_string), Induk = sha256(left_raw || right_raw), daun ganjil diduplikasi.
 */
final class MerkleTree
{
    /**
     * Membangun Merkle Tree dari daftar daun string.
     *
     * @param  list<string>  $leaves
     */
    public function build(array $leaves): MerkleTreeResult
    {
        if (empty($leaves)) {
            return new MerkleTreeResult(root: '', layers: [], leaves: []);
        }

        $layers = [];
        $currentLayer = array_map(static fn (string $leaf): string => hash('sha256', $leaf, true), $leaves);
        $layers[] = $currentLayer;

        while (count($currentLayer) > 1) {
            $nextLayer = [];
            $count = count($currentLayer);
            for ($i = 0; $i < $count; $i += 2) {
                $left = $currentLayer[$i];
                $right = ($i + 1 < $count) ? $currentLayer[$i + 1] : $left;
                $nextLayer[] = hash('sha256', $left.$right, true);
            }
            $currentLayer = $nextLayer;
            $layers[] = $currentLayer;
        }

        $rootHex = bin2hex($currentLayer[0]);

        return new MerkleTreeResult(root: $rootHex, layers: $layers, leaves: $leaves);
    }

    /**
     * Menghasilkan bukti Merkle (Merkle proof) untuk daun pada indeks tertentu.
     *
     * @return list<array{position: 'left'|'right', hash: string}>
     */
    public function getProof(MerkleTreeResult $tree, int $leafIndex): array
    {
        if ($leafIndex < 0 || $leafIndex >= count($tree->leaves)) {
            throw new InvalidArgumentException("Indeks daun di luar jangkauan: {$leafIndex}");
        }

        $proof = [];
        $idx = $leafIndex;
        $totalLayers = count($tree->layers);

        for ($level = 0; $level < $totalLayers - 1; $level++) {
            $layer = $tree->layers[$level];
            $isRight = ($idx % 2 === 1);
            $siblingIdx = $isRight ? ($idx - 1) : ($idx + 1);

            if ($siblingIdx >= count($layer)) {
                $sibling = $layer[$idx]; // duplicate of odd leaf
            } else {
                $sibling = $layer[$siblingIdx];
            }

            $proof[] = [
                'position' => $isRight ? 'left' : 'right',
                'hash' => bin2hex($sibling),
            ];

            $idx = intdiv($idx, 2);
        }

        return $proof;
    }
}
