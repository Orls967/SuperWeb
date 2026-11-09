<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Backfill;

final class BackfillIntegrityChecksumVerifier
{
    /**
     * Computes deterministic SHA256 batch hash over source records.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function computeBatchChecksum(array $rows, array $keyColumns = ['id', 'updated_at']): string
    {
        $fingerprints = [];

        foreach ($rows as $row) {
            $extracted = [];
            foreach ($keyColumns as $col) {
                $extracted[$col] = $row[$col] ?? '';
            }
            ksort($extracted);
            $fingerprints[] = json_encode($extracted, JSON_THROW_ON_ERROR);
        }

        sort($fingerprints);

        return hash('sha256', implode('|', $fingerprints));
    }

    /**
     * Verifies that source and destination batch checksums match 100%.
     */
    public function verifyChecksumsMatch(string $sourceChecksum, string $destinationChecksum): bool
    {
        return hash_equals($sourceChecksum, $destinationChecksum);
    }
}
