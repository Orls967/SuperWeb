<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Hash;

/**
 * Nilai hasil komputasi Merkle Tree.
 */
final readonly class MerkleTreeResult
{
    /**
     * @param  string  $root  Hexadecimal representation of the root hash (64 chars)
     * @param  list<list<string>>  $layers  Layered hashes in binary raw bytes
     * @param  list<string>  $leaves  Original leaf strings
     */
    public function __construct(
        public string $root,
        public array $layers,
        public array $leaves,
    ) {}
}
