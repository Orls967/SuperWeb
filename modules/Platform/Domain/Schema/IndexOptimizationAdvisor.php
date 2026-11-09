<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Schema;

final class IndexOptimizationAdvisor
{
    /**
     * Advises optimal index column order based on cardinality and query predicates (equality before range).
     *
     * @param  list<array{column: string, cardinality: int, predicate_type: string}>  $predicates
     * @return list<string> ordered index columns
     */
    public function adviseColumnOrder(array $predicates): array
    {
        // Sort equality predicates first with highest cardinality, then range predicates
        usort($predicates, function (array $a, array $b): int {
            $isEqualityA = ($a['predicate_type'] ?? '') === 'EQUALITY';
            $isEqualityB = ($b['predicate_type'] ?? '') === 'EQUALITY';

            if ($isEqualityA && ! $isEqualityB) {
                return -1;
            }
            if (! $isEqualityA && $isEqualityB) {
                return 1;
            }

            return ($b['cardinality'] ?? 0) <=> ($a['cardinality'] ?? 0);
        });

        return array_column($predicates, 'column');
    }
}
