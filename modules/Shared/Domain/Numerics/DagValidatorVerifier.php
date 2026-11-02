<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Numerics;

use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Verifier for DagValidator certificates.
 *
 * Verifies:
 * 1. If acyclic: topological sort order contains all vertices exactly once and strictly respects every directed edge.
 * 2. If cyclic: claimed cycle path forms a valid continuous directed cycle in the graph.
 * 3. Node and edge counts consistency.
 */
final class DagValidatorVerifier
{
    /**
     * @param  array{
     *     is_acyclic: bool,
     *     nodes_count: int,
     *     edges_count: int,
     *     topological_order: list<string>|null,
     *     cycle_path: list<string>|null
     * }  $cert
     * @param  array<string|int, list<string|int>>  $adjList
     */
    public static function verifyCertificate(array $cert, array $adjList): bool
    {
        if (! isset($cert['is_acyclic'], $cert['nodes_count'], $cert['edges_count'])) {
            throw new InvalidArgumentException('Certificate missing required keys.');
        }

        $adj = DagValidator::normalizeAdjList($adjList);
        $nodes = array_keys($adj);
        $expectedNodeCount = count($nodes);

        $expectedEdgeCount = 0;
        foreach ($adj as $neighbors) {
            $expectedEdgeCount += count($neighbors);
        }

        if ($cert['nodes_count'] !== $expectedNodeCount) {
            throw new UnexpectedValueException("Certificate nodes_count mismatch: expected {$expectedNodeCount}, got {$cert['nodes_count']}.");
        }

        if ($cert['edges_count'] !== $expectedEdgeCount) {
            throw new UnexpectedValueException("Certificate edges_count mismatch: expected {$expectedEdgeCount}, got {$cert['edges_count']}.");
        }

        if ($cert['is_acyclic']) {
            if ($cert['cycle_path'] !== null) {
                throw new UnexpectedValueException('Acyclic certificate must have null cycle_path.');
            }
            if (! is_array($cert['topological_order'])) {
                throw new UnexpectedValueException('Acyclic certificate must have array topological_order.');
            }

            $order = $cert['topological_order'];
            if (count($order) !== $expectedNodeCount) {
                throw new UnexpectedValueException('Topological order length mismatch.');
            }

            $pos = [];
            foreach ($order as $idx => $node) {
                $nodeStr = (string) $node;
                if (! isset($adj[$nodeStr])) {
                    throw new UnexpectedValueException("Unknown node '{$nodeStr}' in topological order.");
                }
                if (isset($pos[$nodeStr])) {
                    throw new UnexpectedValueException("Duplicate node '{$nodeStr}' in topological order.");
                }
                $pos[$nodeStr] = $idx;
            }

            foreach ($adj as $u => $neighbors) {
                foreach ($neighbors as $v) {
                    if ($pos[$u] >= $pos[$v]) {
                        throw new UnexpectedValueException("Topological order violation: edge {$u} -> {$v} violated ({$pos[$u]} >= {$pos[$v]}).");
                    }
                }
            }
        } else {
            if ($cert['topological_order'] !== null) {
                throw new UnexpectedValueException('Cyclic certificate must have null topological_order.');
            }
            if (! is_array($cert['cycle_path']) || count($cert['cycle_path']) < 2) {
                throw new UnexpectedValueException('Cyclic certificate must have a valid cycle_path of length >= 2.');
            }

            $cycle = $cert['cycle_path'];
            $len = count($cycle);
            if ($cycle[0] !== $cycle[$len - 1]) {
                throw new UnexpectedValueException("Cycle path is not closed: starts with '{$cycle[0]}' but ends with '{$cycle[$len - 1]}'.");
            }

            for ($i = 0; $i < $len - 1; $i++) {
                $u = (string) $cycle[$i];
                $v = (string) $cycle[$i + 1];

                if (! isset($adj[$u]) || ! in_array($v, $adj[$u], true)) {
                    throw new UnexpectedValueException("Cycle path contains non-existent edge: {$u} -> {$v}.");
                }
            }
        }

        return true;
    }
}
