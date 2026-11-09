<?php

declare(strict_types=1);

namespace App\Quality\Algorithms;

final class ReachabilityReport
{
    /**
     * @param  list<string>  $reachableClasses
     * @param  list<string>  $orphanClasses
     * @param  array<string, list<string>>  $adjacencyList
     */
    public function __construct(
        public readonly array $reachableClasses,
        public readonly array $orphanClasses,
        public readonly array $adjacencyList,
        public readonly int $totalNodes,
        public readonly int $totalEdges,
        public readonly string $certificate = '',
    ) {}

    public function isOrphan(string $className): bool
    {
        return in_array($className, $this->orphanClasses, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total_nodes' => $this->totalNodes,
            'total_edges' => $this->totalEdges,
            'reachable_count' => count($this->reachableClasses),
            'orphan_count' => count($this->orphanClasses),
            'orphans' => $this->orphanClasses,
            'certificate' => $this->certificate,
        ];
    }
}
