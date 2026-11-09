<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Change;

final class ChangeDependencyMatrixResolver
{
    /**
     * Resolves topological deployment order for changes having inter-dependencies.
     *
     * @param  array<int, list<int>>  $dependencies  ChangeID => list of dependency ChangeIDs
     * @return array{order: list<int>, has_cycle: bool}
     */
    public function resolveOrder(array $dependencies): array
    {
        $inDegree = [];
        $adj = [];
        $allNodes = array_keys($dependencies);

        foreach ($dependencies as $node => $deps) {
            foreach ($deps as $d) {
                $allNodes[] = $d;
            }
        }
        $allNodes = array_values(array_unique($allNodes));

        foreach ($allNodes as $n) {
            $inDegree[$n] = 0;
            $adj[$n] = [];
        }

        foreach ($dependencies as $node => $deps) {
            foreach ($deps as $dep) {
                // dep must happen before node
                $adj[$dep][] = $node;
                $inDegree[$node]++;
            }
        }

        $queue = [];
        foreach ($allNodes as $n) {
            if ($inDegree[$n] === 0) {
                $queue[] = $n;
            }
        }

        $order = [];
        while ($queue !== []) {
            $curr = array_shift($queue);
            $order[] = $curr;

            foreach ($adj[$curr] as $neighbor) {
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] === 0) {
                    $queue[] = $neighbor;
                }
            }
        }

        $hasCycle = (count($order) !== count($allNodes));

        return [
            'order' => $order,
            'has_cycle' => $hasCycle,
        ];
    }
}
