<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Services;

/**
 * Compiler skema relasional tabel database untuk pemetaan urutan generasi data sintetis.
 */
class SyntheticDataSchemaCompiler
{
    /**
     * @param  array<string, list<string>>  $foreignKeyDependencies  Map table -> [parent_tables]
     * @return list<string> Topological sort urutan insersi tabel bebas deadlock foreign key
     */
    public function determineInsertionOrder(array $foreignKeyDependencies): array
    {
        $inDegree = [];
        $adjacency = [];
        $tables = array_keys($foreignKeyDependencies);

        foreach ($tables as $t) {
            $inDegree[$t] = 0;
            $adjacency[$t] = [];
        }

        foreach ($foreignKeyDependencies as $child => $parents) {
            foreach ($parents as $p) {
                if (isset($adjacency[$p])) {
                    $adjacency[$p][] = $child;
                    $inDegree[$child]++;
                }
            }
        }

        $queue = [];
        foreach ($inDegree as $t => $deg) {
            if ($deg === 0) {
                $queue[] = $t;
            }
        }

        $order = [];
        while (! empty($queue)) {
            $curr = array_shift($queue);
            $order[] = $curr;

            foreach ($adjacency[$curr] as $neighbor) {
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] === 0) {
                    $queue[] = $neighbor;
                }
            }
        }

        // Jika ada tabel siklis yang belum masuk, tambahkan di akhir
        foreach ($tables as $t) {
            if (! in_array($t, $order, true)) {
                $order[] = $t;
            }
        }

        return $order;
    }
}
