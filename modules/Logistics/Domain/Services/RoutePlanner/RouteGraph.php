<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Services\RoutePlanner;

class RouteGraph
{
    /**
     * @var array<int, RouteNode>
     */
    private array $nodes = [];

    /**
     * @var array<int, array<int, RouteEdge>>
     */
    private array $adjacency = [];

    public function addNode(RouteNode $node): void
    {
        $this->nodes[$node->locationId] = $node;
    }

    public function addEdge(RouteEdge $edge): void
    {
        $this->adjacency[$edge->originLocationId][] = $edge;
    }

    public function getNode(int $locationId): ?RouteNode
    {
        return $this->nodes[$locationId] ?? null;
    }

    /**
     * @return array<int, RouteEdge>
     */
    public function getOutgoingEdges(int $originLocationId): array
    {
        return $this->adjacency[$originLocationId] ?? [];
    }

    public function nodeCount(): int
    {
        return count($this->nodes);
    }

    public function edgeCount(): int
    {
        $total = 0;
        foreach ($this->adjacency as $edges) {
            $total += count($edges);
        }

        return $total;
    }
}
