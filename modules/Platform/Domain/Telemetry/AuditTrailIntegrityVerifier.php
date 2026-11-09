<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Telemetry;

/**
 * Pemeriksa integritas rantai kriptografis (Cryptographic Hash Chain) log audit platform.
 */
class AuditTrailIntegrityVerifier
{
    /**
     * @param list<array{id: int, prev_hash: string, payload: string, hash: string}> $entries
     * @return array{is_valid: bool, broken_at_index: ?int, total_verified: int}
     */
    public function verifyHashChain(array $entries): array
    {
        $expectedPrev = 'GENESIS_HASH_00000000000000000000000000000000';
        $verified = 0;

        foreach ($entries as $idx => $entry) {
            if ($entry['prev_hash'] !== $expectedPrev) {
                return [
                    'is_valid' => false,
                    'broken_at_index' => $idx,
                    'total_verified' => $verified,
                ];
            }

            $computedHash = hash('sha256', "{$entry['prev_hash']}:{$entry['payload']}");
            if (! hash_equals($computedHash, $entry['hash'])) {
                return [
                    'is_valid' => false,
                    'broken_at_index' => $idx,
                    'total_verified' => $verified,
                ];
            }

            $expectedPrev = $entry['hash'];
            $verified++;
        }

        return [
            'is_valid' => true,
            'broken_at_index' => null,
            'total_verified' => $verified,
        ];
    }
}

