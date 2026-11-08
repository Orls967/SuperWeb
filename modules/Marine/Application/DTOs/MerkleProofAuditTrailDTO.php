<?php

declare(strict_types=1);

namespace Modules\Marine\Application\DTOs;

use DomainException;

/**
 * DTO jejak audit bukti kriptografis Merkle Tree (Fase 171).
 */
class MerkleProofAuditTrailDTO
{
    /**
     * @param  array<int, array{position: string, hash: string}>  $proofPath
     * @param  array<string, mixed>  $stageMetadata
     */
    public function __construct(
        public readonly string $nodeUuid,
        public readonly int $exportLotId,
        public readonly int $leafIndex,
        public readonly string $stageName,
        public readonly string $leafHash,
        public readonly string $merkleRootHash,
        public readonly array $proofPath,
        public readonly array $stageMetadata,
        public readonly string $anchoredAt
    ) {
        $this->validate();
    }

    protected function validate(): void
    {
        if (strlen($this->leafHash) !== 64 || strlen($this->merkleRootHash) !== 64) {
            throw new DomainException('Hash daun dan Merkle Root harus tepat 64 karakter hex SHA-256.');
        }

        if ($this->leafIndex < 0) {
            throw new DomainException('Leaf index tidak boleh negatif.');
        }
    }

    public function getProofStepCount(): int
    {
        return count($this->proofPath);
    }
}
