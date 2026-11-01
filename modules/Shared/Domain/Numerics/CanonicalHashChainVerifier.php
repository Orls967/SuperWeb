<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Numerics;

/**
 * Verifies the integrity of a hash-chain, returning the 0-based index
 * of the first corrupted record, or null if the chain is completely intact.
 */
final class CanonicalHashChainVerifier
{
    /**
     * @param  list<array{prev_hash: string, hash: string, payload: array<string, mixed>}>  $records
     * @return int|null Index of first corrupted link, or null if valid.
     */
    public static function verifyChain(array $records): ?int
    {
        $expectedPrevHash = CanonicalHashChain::GENESIS_PREV_HASH;

        foreach ($records as $index => $record) {
            $prevHash = $record['prev_hash'] ?? '';
            $currentHash = $record['hash'] ?? '';
            $payload = $record['payload'] ?? [];

            if ($prevHash !== $expectedPrevHash) {
                return $index;
            }

            try {
                $computed = CanonicalHashChain::computeHash($prevHash, $payload);
            } catch (\Throwable) {
                return $index;
            }

            if (! hash_equals($computed, $currentHash)) {
                return $index;
            }

            $expectedPrevHash = $currentHash;
        }

        return null;
    }
}
