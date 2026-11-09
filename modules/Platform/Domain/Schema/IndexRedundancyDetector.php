<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Schema;

final class IndexRedundancyDetector
{
    /**
     * Checks if a new index definition is redundant given existing composite indices.
     * For example, index on `[A]` is redundant if composite index on `[A, B]` exists.
     *
     * @param  list<list<string>>  $existingIndices  e.g. [['user_id', 'created_at'], ['status']]
     * @param  list<string>  $newIndexColumns  e.g. ['user_id']
     * @return array{is_redundant: bool, shadowed_by: ?list<string>}
     */
    public function isRedundant(array $existingIndices, array $newIndexColumns): array
    {
        $newLen = count($newIndexColumns);
        if ($newLen === 0) {
            return ['is_redundant' => true, 'shadowed_by' => null];
        }

        foreach ($existingIndices as $existing) {
            $existingLen = count($existing);
            if ($existingLen >= $newLen) {
                // Check if newIndexColumns is a prefix of existing index
                $prefix = array_slice($existing, 0, $newLen);
                if ($prefix === $newIndexColumns) {
                    return [
                        'is_redundant' => true,
                        'shadowed_by' => $existing,
                    ];
                }
            }
        }

        return [
            'is_redundant' => false,
            'shadowed_by' => null,
        ];
    }
}
