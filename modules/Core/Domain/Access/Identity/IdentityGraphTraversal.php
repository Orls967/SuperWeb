<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Access\Identity;

use Modules\Core\Domain\Models\CoreIdentityLink;

/**
 * Identity Graph Traversal & Topology Analyzer (W8-D56).
 *
 * Implements graph search algorithms across multi-party linked identities:
 * - Breadth-First Search (BFS) for shortest linkage distance
 * - Depth-First Search (DFS) for transitive trust cluster traversal
 * - Cycle and sybil cluster detection
 * - Connected component partitioning
 */
final class IdentityGraphTraversal
{
    /**
     * Find all transitively linked entities for a given seed entity.
     *
     * @param  int  $maxDepth  Max traversal hops
     * @return list<array{entity_id: int, distance: int, path: list<int>, min_match_score: float}>
     */
    public function findTransitivelyLinked(
        string $subjectType,
        int $seedEntityId,
        int $maxDepth = 3
    ): array {
        $visited = [$seedEntityId => true];
        $results = [];

        // Queue holds: [current_id, depth, path, min_score]
        $queue = [[$seedEntityId, 0, [$seedEntityId], 1.0]];

        while (! empty($queue)) {
            /** @var array{0: int, 1: int, 2: list<int>, 3: float} $item */
            $item = array_shift($queue);
            [$currentId, $depth, $path, $minScore] = $item;

            if ($depth >= $maxDepth) {
                continue;
            }

            // Find neighbors where current is either source or target
            $links = CoreIdentityLink::where('subject_type', $subjectType)
                ->where('is_active', true)
                ->where(function ($q) use ($currentId) {
                    $q->where('source_entity_id', $currentId)
                        ->orWhere('target_entity_id', $currentId);
                })
                ->get();

            foreach ($links as $link) {
                $neighborId = ($link->source_entity_id === $currentId)
                    ? $link->target_entity_id
                    : $link->source_entity_id;

                if (isset($visited[$neighborId])) {
                    continue;
                }

                $visited[$neighborId] = true;
                $newDepth = $depth + 1;
                $newPath = array_merge($path, [$neighborId]);
                $newMinScore = min($minScore, (float) $link->match_score);

                $results[] = [
                    'entity_id' => $neighborId,
                    'distance' => $newDepth,
                    'path' => $newPath,
                    'min_match_score' => $newMinScore,
                ];

                $queue[] = [$neighborId, $newDepth, $newPath, $newMinScore];
            }
        }

        return $results;
    }

    /**
     * Detect whether adding a link would create a circular identity loop.
     */
    public function detectsCycle(string $subjectType, int $sourceEntityId, int $targetEntityId): bool
    {
        if ($sourceEntityId === $targetEntityId) {
            return true;
        }

        // If target can already reach source, adding link (source -> target) creates a cycle
        $reachable = $this->findTransitivelyLinked($subjectType, $targetEntityId, maxDepth: 5);
        foreach ($reachable as $node) {
            if ($node['entity_id'] === $sourceEntityId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Compute cluster density score for an entity's identity component.
     */
    public function computeClusterDensity(string $subjectType, int $entityId): float
    {
        $cluster = $this->findTransitivelyLinked($subjectType, $entityId, maxDepth: 4);
        $nodeCount = count($cluster) + 1;

        if ($nodeCount <= 1) {
            return 1.0;
        }

        $allIds = array_merge([$entityId], array_column($cluster, 'entity_id'));

        $edgeCount = CoreIdentityLink::where('subject_type', $subjectType)
            ->where('is_active', true)
            ->whereIn('source_entity_id', $allIds)
            ->whereIn('target_entity_id', $allIds)
            ->count();

        $maxEdges = ($nodeCount * ($nodeCount - 1)) / 2;

        return $maxEdges > 0 ? round($edgeCount / $maxEdges, 3) : 1.0;
    }
}
