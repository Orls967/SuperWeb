<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Marine;

use DomainException;
use InvalidArgumentException;

/**
 * A17: SeafoodLotMerkleProofGenerator.
 *
 * Menghasilkan pohon Merkle kriptografis untuk bukti ketelusuran komoditas perikanan dan udang ekspor.
 * Setiap simpul daun (leaf node) mewakili satu tahapan rantai pasok (benur, tambak, pakan, panen,
 * cold storage, sertifikat fitosanitari, hingga pemuatan reefer container).
 */
class SeafoodLotMerkleProofGenerator
{
    /**
     * Bangun Merkle Tree dari daftar catatan ketelusuran (leaves).
     *
     * @param  array<int, array<string, mixed>|string>  $traceEvents
     * @return array{
     *     root_hash: string,
     *     leaf_count: int,
     *     tree_depth: int,
     *     leaves: array<int, string>,
     *     levels: array<int, array<int, string>>
     * }
     */
    public function buildMerkleTree(array $traceEvents): array
    {
        if (empty($traceEvents)) {
            throw new DomainException('Daftar peristiwa ketelusuran tidak boleh kosong.');
        }

        $leaves = [];
        foreach ($traceEvents as $event) {
            $serialized = is_array($event) ? json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (string) $event;
            $leaves[] = hash('sha256', (string) $serialized);
        }

        $levels = [];
        $currentLevel = $leaves;
        $levels[] = $currentLevel;

        while (count($currentLevel) > 1) {
            $nextLevel = [];
            $count = count($currentLevel);

            for ($i = 0; $i < $count; $i += 2) {
                $left = $currentLevel[$i];
                $right = ($i + 1 < $count) ? $currentLevel[$i + 1] : $left; // Gandakan ganjil
                $combined = $left <= $right ? $left.$right : $right.$left; // Canonical sorted pair
                $nextLevel[] = hash('sha256', $combined);
            }

            $levels[] = $nextLevel;
            $currentLevel = $nextLevel;
        }

        return [
            'root_hash' => $currentLevel[0],
            'leaf_count' => count($leaves),
            'tree_depth' => count($levels),
            'leaves' => $leaves,
            'levels' => $levels,
        ];
    }

    /**
     * Dapatkan path audit cryptographic proof untuk sebuah daun (leaf index).
     *
     * @param  array{levels: array<int, array<int, string>>}  $tree
     * @return array<int, array{position: string, hash: string}>
     */
    public function generateProof(array $tree, int $leafIndex): array
    {
        $levels = $tree['levels'] ?? [];
        if (empty($levels)) {
            throw new InvalidArgumentException('Struktur Merkle Tree tidak valid.');
        }

        $leafCount = count($levels[0]);
        if ($leafIndex < 0 || $leafIndex >= $leafCount) {
            throw new DomainException("Indeks daun {$leafIndex} di luar batas pohon (0..{$leafCount}).");
        }

        $proof = [];
        $currentIndex = $leafIndex;

        for ($depth = 0; $depth < count($levels) - 1; $depth++) {
            $level = $levels[$depth];
            $isRightSibling = ($currentIndex % 2 === 1);
            $siblingIndex = $isRightSibling ? $currentIndex - 1 : $currentIndex + 1;

            if ($siblingIndex < count($level)) {
                $proof[] = [
                    'position' => $isRightSibling ? 'left' : 'right',
                    'hash' => $level[$siblingIndex],
                ];
            } else {
                // Leaf ganjil di ujung kanan, pasangannya adalah dirinya sendiri
                $proof[] = [
                    'position' => 'right',
                    'hash' => $level[$currentIndex],
                ];
            }

            $currentIndex = intdiv($currentIndex, 2);
        }

        return $proof;
    }

    /**
     * Verifikasi keaslian bukti daun terhadap Merkle Root Hash.
     *
     * @param  array<int, array{position: string, hash: string}>  $proof
     */
    public function verifyProof(string $leafHash, array $proof, string $expectedRootHash): bool
    {
        $computedHash = $leafHash;

        foreach ($proof as $step) {
            $siblingHash = $step['hash'];
            $position = $step['position'];

            if ($position === 'left') {
                $combined = $siblingHash <= $computedHash ? $siblingHash.$computedHash : $computedHash.$siblingHash;
            } else {
                $combined = $computedHash <= $siblingHash ? $computedHash.$siblingHash : $siblingHash.$computedHash;
            }

            $computedHash = hash('sha256', $combined);
        }

        return hash_equals($expectedRootHash, $computedHash);
    }
}
