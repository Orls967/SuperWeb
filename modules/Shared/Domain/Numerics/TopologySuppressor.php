<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Numerics;

use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Topology-Based Network Alarm Suppressor.
 *
 * Uses directed acyclic graph (DAG) traversal to suppress downstream cascade alarms
 * when an upstream root-cause node is down or critical.
 *
 * Complexity: O(V + E) for topological validation and BFS suppression propagation.
 */
final class TopologySuppressor
{
    /**
     * @param  array<string|int, list<string|int>>|list<array{parent: string|int, child: string|int}>  $topology
     *                                                                                                            Network hierarchy where directed edges go from upstream (parent) to downstream (child)
     * @param  list<array{
     *     id: string|int,
     *     node: string|int,
     *     severity: string,
     *     timestamp?: string|int
     * }>  $alarms Active network alarms
     * @param  list<string>  $downSeverities  Severity levels considered "down" (default: ['down', 'critical'])
     * @return array{
     *     total_alarms: int,
     *     root_causes_count: int,
     *     suppressed_count: int,
     *     independent_count: int,
     *     tickets: list<array{
     *         ticket_id: string,
     *         root_node: string|int,
     *         alarm_id: string|int,
     *         severity: string,
     *         suppressed_alarm_ids: list<string|int>
     *     }>,
     *     alarms: list<array{
     *         id: string|int,
     *         node: string|int,
     *         severity: string,
     *         status: 'root_cause'|'suppressed'|'independent',
     *         ticket_id: string|null,
     *         root_cause_node: string|int|null,
     *         ancestor_path: list<string|int>|null
     *     }>,
     *     certificate: array{
     *         topology_nodes_count: int,
     *         topology_edges_count: int,
     *         total_alarms: int,
     *         root_causes_count: int,
     *         suppressed_count: int,
     *         tickets_count: int,
     *         alarms: list<array{
     *             id: string|int,
     *             node: string|int,
     *             severity: string,
     *             status: 'root_cause'|'suppressed'|'independent',
     *             ticket_id: string|null,
     *             root_cause_node: string|int|null,
     *             ancestor_path: list<string|int>|null
     *         }>
     *     }
     * }
     */
    public static function suppress(
        array $topology,
        array $alarms,
        array $downSeverities = ['down', 'critical'],
    ): array {
        $adj = [];
        $revAdj = [];
        $allNodes = [];
        $edgesCount = 0;

        // Normalize topology input
        foreach ($topology as $key => $value) {
            if (is_array($value) && isset($value['parent'], $value['child'])) {
                $p = (string) $value['parent'];
                $c = (string) $value['child'];
                $adj[$p][] = $c;
                $revAdj[$c][] = $p;
                $allNodes[$p] = true;
                $allNodes[$c] = true;
                $edgesCount++;
            } elseif (is_array($value)) {
                $p = (string) $key;
                $allNodes[$p] = true;
                foreach ($value as $cNode) {
                    $c = (string) $cNode;
                    $adj[$p][] = $c;
                    $revAdj[$c][] = $p;
                    $allNodes[$c] = true;
                    $edgesCount++;
                }
            } else {
                throw new InvalidArgumentException("Invalid topology format near key '{$key}'");
            }
        }

        // Validate DAG (cycle detection via Kahn's algorithm O(V + E))
        self::assertNoCycles($adj, array_keys($allNodes));

        $downMap = array_fill_keys(array_map('strtolower', $downSeverities), true);

        // Sort alarms deterministically by ID
        $sortedAlarms = $alarms;
        usort($sortedAlarms, function ($a, $b) {
            $idA = (string) $a['id'];
            $idB = (string) $b['id'];

            return strcmp($idA, $idB);
        });

        // Group alarms by node string
        /** @var array<string, list<array{id: string|int, node: string|int, severity: string}>> $nodeAlarms */
        $nodeAlarms = [];
        foreach ($sortedAlarms as $alarm) {
            $nodeStr = (string) $alarm['node'];
            $nodeAlarms[$nodeStr][] = $alarm;
        }

        // Identify down nodes
        $isNodeDown = [];
        foreach ($nodeAlarms as $nodeStr => $list) {
            foreach ($list as $al) {
                if (isset($downMap[strtolower($al['severity'])])) {
                    $isNodeDown[$nodeStr] = true;
                    break;
                }
            }
        }

        // Find root cause down nodes: down nodes with NO down ancestor
        $rootCauseDownNodes = [];
        $downNodeKeys = array_keys($isNodeDown);
        sort($downNodeKeys); // deterministic tie-break

        foreach ($downNodeKeys as $nodeKey) {
            $nodeStr = (string) $nodeKey;
            if (! self::hasDownAncestor($nodeStr, $revAdj, $isNodeDown)) {
                $rootCauseDownNodes[] = $nodeStr;
            }
        }

        // For each root cause node, propagate suppression downward via $O(1)$-pop BFS
        $suppressionMap = [];
        foreach ($rootCauseDownNodes as $rootNode) {
            $queue = [[$rootNode, [$rootNode]]];
            $head = 0;
            $visited = [$rootNode => true];

            while ($head < count($queue)) {
                [$curr, $path] = $queue[$head++];
                $children = $adj[$curr] ?? [];

                foreach ($children as $child) {
                    if (isset($visited[$child])) {
                        continue;
                    }
                    $visited[$child] = true;
                    $childPath = array_merge($path, [$child]);

                    // If child not already suppressed by an earlier root cause, claim it
                    if (! isset($suppressionMap[$child])) {
                        $suppressionMap[$child] = [
                            'root' => $rootNode,
                            'path' => $childPath,
                        ];
                    }

                    $queue[] = [$child, $childPath];
                }
            }
        }

        // Build tickets and classified alarm list
        $tickets = [];
        $ticketMap = []; // rootNode => ticket
        $classifiedAlarms = [];
        $rootCausesCount = 0;
        $suppressedCount = 0;
        $independentCount = 0;

        // First pass: create tickets for root cause alarms
        foreach ($sortedAlarms as $alarm) {
            $nodeStr = (string) $alarm['node'];
            $sevLower = strtolower($alarm['severity']);
            $isDown = isset($downMap[$sevLower]);

            if ($isDown && in_array($nodeStr, $rootCauseDownNodes, true)) {
                if (! isset($ticketMap[$nodeStr])) {
                    $ticketId = 'TKT-'.$nodeStr;
                    $ticketMap[$nodeStr] = [
                        'ticket_id' => $ticketId,
                        'root_node' => $alarm['node'],
                        'alarm_id' => $alarm['id'],
                        'severity' => $alarm['severity'],
                        'suppressed_alarm_ids' => [],
                    ];
                }
            }
        }

        // Second pass: classify all alarms
        foreach ($sortedAlarms as $alarm) {
            $nodeStr = (string) $alarm['node'];
            $sevLower = strtolower($alarm['severity']);
            $isDown = isset($downMap[$sevLower]);

            if (isset($suppressionMap[$nodeStr])) {
                // Downstream of a root cause -> suppressed!
                $suppInfo = $suppressionMap[$nodeStr];
                $rootNode = $suppInfo['root'];
                $ticketId = $ticketMap[$rootNode]['ticket_id'] ?? ('TKT-'.$rootNode);

                $classifiedAlarms[] = [
                    'id' => $alarm['id'],
                    'node' => $alarm['node'],
                    'severity' => $alarm['severity'],
                    'status' => 'suppressed',
                    'ticket_id' => $ticketId,
                    'root_cause_node' => $rootNode,
                    'ancestor_path' => $suppInfo['path'],
                ];
                $suppressedCount++;

                if (isset($ticketMap[$rootNode])) {
                    $ticketMap[$rootNode]['suppressed_alarm_ids'][] = $alarm['id'];
                }
            } elseif ($isDown && in_array($nodeStr, $rootCauseDownNodes, true)) {
                // It is a root cause
                $ticketId = $ticketMap[$nodeStr]['ticket_id'] ?? ('TKT-'.$nodeStr);
                $classifiedAlarms[] = [
                    'id' => $alarm['id'],
                    'node' => $alarm['node'],
                    'severity' => $alarm['severity'],
                    'status' => 'root_cause',
                    'ticket_id' => $ticketId,
                    'root_cause_node' => null,
                    'ancestor_path' => null,
                ];
                $rootCausesCount++;
            } else {
                // Not suppressed, but also not down (e.g. warning with no down ancestor)
                $classifiedAlarms[] = [
                    'id' => $alarm['id'],
                    'node' => $alarm['node'],
                    'severity' => $alarm['severity'],
                    'status' => 'independent',
                    'ticket_id' => null,
                    'root_cause_node' => null,
                    'ancestor_path' => null,
                ];
                $independentCount++;
            }
        }

        $tickets = array_values($ticketMap);

        $certificate = [
            'topology_nodes_count' => count($allNodes),
            'topology_edges_count' => $edgesCount,
            'total_alarms' => count($alarms),
            'root_causes_count' => $rootCausesCount,
            'suppressed_count' => $suppressedCount,
            'tickets_count' => count($tickets),
            'alarms' => $classifiedAlarms,
        ];

        $result = [
            'total_alarms' => count($alarms),
            'root_causes_count' => $rootCausesCount,
            'suppressed_count' => $suppressedCount,
            'independent_count' => $independentCount,
            'tickets' => $tickets,
            'alarms' => $classifiedAlarms,
            'certificate' => $certificate,
        ];

        TopologySuppressorVerifier::verify($result, $topology, $downSeverities);

        return $result;
    }

    /**
     * @param  array<string, list<string>>  $revAdj
     * @param  array<string, bool>  $isNodeDown
     */
    private static function hasDownAncestor(string|int $startNode, array $revAdj, array $isNodeDown): bool
    {
        $queue = [(string) $startNode];
        $head = 0;
        $visited = [(string) $startNode => true];

        while ($head < count($queue)) {
            $curr = $queue[$head++];
            $parents = $revAdj[$curr] ?? [];

            foreach ($parents as $p) {
                if (isset($isNodeDown[$p])) {
                    return true;
                }
                if (! isset($visited[$p])) {
                    $visited[$p] = true;
                    $queue[] = $p;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, list<string>>  $adj
     * @param  list<string>  $nodes
     */
    private static function assertNoCycles(array $adj, array $nodes): void
    {
        $inDegree = [];
        foreach ($nodes as $node) {
            $inDegree[$node] = 0;
        }
        foreach ($adj as $u => $children) {
            foreach ($children as $v) {
                $inDegree[$v] = ($inDegree[$v] ?? 0) + 1;
            }
        }

        $q = [];
        foreach ($inDegree as $node => $deg) {
            if ($deg === 0) {
                $q[] = $node;
            }
        }

        $head = 0;
        $visitedCount = 0;
        while ($head < count($q)) {
            $u = $q[$head++];
            $visitedCount++;
            foreach ($adj[$u] ?? [] as $v) {
                $inDegree[$v]--;
                if ($inDegree[$v] === 0) {
                    $q[] = $v;
                }
            }
        }

        if ($visitedCount !== count($nodes)) {
            throw new UnexpectedValueException('Topology graph contains cycle');
        }
    }
}
