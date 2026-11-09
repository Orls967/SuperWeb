<?php

declare(strict_types=1);

namespace App\Quality\Algorithms;

/**
 * Struktur Data Index Fingerprint Terbalik (Inverted Hash Index) untuk pencarian duplikasi skala luas.
 */
final class CodeDuplicationFingerprintIndex
{
    /** @var array<int, list<array{file: string, offset: int}>> */
    private array $hashTable = [];

    public function insert(int $hash, string $file, int $offset): void
    {
        $this->hashTable[$hash][] = [
            'file' => $file,
            'offset' => $offset,
        ];
    }

    /**
     * @return list<array{file: string, offset: int}>
     */
    public function lookup(int $hash): array
    {
        return $this->hashTable[$hash] ?? [];
    }

    /**
     * Mengembalikan daftar hash yang memiliki kemunculan jamak (kandidat duplikasi)
     *
     * @return array<int, list<array{file: string, offset: int}>>
     */
    public function getCollisionCandidates(): array
    {
        $candidates = [];
        foreach ($this->hashTable as $hash => $locations) {
            if (count($locations) >= 2) {
                $candidates[$hash] = $locations;
            }
        }

        return $candidates;
    }

    public function clear(): void
    {
        $this->hashTable = [];
    }

    public function size(): int
    {
        return count($this->hashTable);
    }
}
