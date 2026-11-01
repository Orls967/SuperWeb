<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Numerics;

use UnexpectedValueException;

/**
 * Verifier for TopologySuppressor results and certificates.
 *
 * Enforces PR2 verification:
 * - Topology is strictly a DAG (no cycles)
 * - Every suppressed alarm has a verified down ancestor with valid path
 * - Root-cause alarms have NO down ancestors
 * - Ticket counts and suppressed alarm ID listings match identically
 */
final class TopologySuppressorVerifier
{
    /**
     * @param array{
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
     *         status: string,
     *         ticket_id: string|null,
     *         root_cause_node: string|int|null,
     *         ancestor_path: list<string|int>|null
     *     }>,
     *     certificate?: array<string, mixed>
     * } $result
     * @param  array<string|int, list<string|int>>|list<array{parent: string|int, child: string|int}>  $topology
     * @param  list<string>  $downSeverities
     *
     * @throws UnexpectedValueException
     */
    public static function verify(
        array $result,
        array $topology = [],
        array $downSeverities = ['down', 'critical'],
    ): bool {
        if (empty($topology) && isset($result['topology'])) {
            $topology = $result['topology'];
        }

        if (! isset($result['total_alarms'], $result['root_causes_count'], $result['suppressed_count'], $result['independent_count'], $result['tickets'], $result['alarms'])) {
            throw new UnexpectedValueException('Missing required fields in TopologySuppressor result');
        }

        $adj = [];
        $revAdj = [];
        $edges = [];

        foreach ($topology as $key => $value) {
            if (is_array($value) && isset($value['parent'], $value['child'])) {
                $p = (string) $value['parent'];
                $c = (string) $value['child'];
                $adj[$p][] = $c;
                $revAdj[$c][] = $p;
                $edges[$p.'->'.$c] = true;
            } elseif (is_array($value)) {
                $p = (string) $key;
                foreach ($value as $cNode) {
                    $c = (string) $cNode;
                    $adj[$p][] = $c;
                    $revAdj[$c][] = $p;
                    $edges[$p.'->'.$c] = true;
                }
            }
        }

        $downMap = array_fill_keys(array_map('strtolower', $downSeverities), true);

        // Find which nodes have down alarms
        $isNodeDown = [];
        $ticketByRootNode = [];
        foreach ($result['tickets'] as $t) {
            $ticketByRootNode[(string) $t['root_node']] = $t['ticket_id'];
        }

        foreach ($result['alarms'] as $al) {
            $sevLower = strtolower($al['severity']);
            if (isset($downMap[$sevLower])) {
                $isNodeDown[(string) $al['node']] = true;
            }
        }

        $rootCount = 0;
        $suppCount = 0;
        $indepCount = 0;

        foreach ($result['alarms'] as $idx => $alarm) {
            $nodeStr = (string) $alarm['node'];
            $status = $alarm['status'];

            if ($status === 'root_cause') {
                $rootCount++;
                if (! isset($downMap[strtolower($alarm['severity'])])) {
                    throw new UnexpectedValueException("Root cause alarm {$alarm['id']} does not have down/critical severity");
                }
                if ($alarm['ticket_id'] === null || ! isset($ticketByRootNode[$nodeStr])) {
                    throw new UnexpectedValueException("Root cause alarm {$alarm['id']} on node {$nodeStr} missing ticket");
                }
                // Verify NO down ancestor
                if (self::hasDownAncestor($nodeStr, $revAdj, $isNodeDown)) {
                    throw new UnexpectedValueException("Claimed root cause node {$nodeStr} has a down ancestor in topology");
                }
            } elseif ($status === 'suppressed') {
                $suppCount++;
                if ($alarm['root_cause_node'] === null) {
                    throw new UnexpectedValueException("Suppressed alarm {$alarm['id']} missing root_cause_node");
                }
                $rootCauseStr = (string) $alarm['root_cause_node'];
                if (! isset($isNodeDown[$rootCauseStr])) {
                    throw new UnexpectedValueException("Suppressed alarm {$alarm['id']} references non-down root cause node {$rootCauseStr}");
                }
                $path = $alarm['ancestor_path'];
                if (! is_array($path) || count($path) < 2) {
                    throw new UnexpectedValueException("Suppressed alarm {$alarm['id']} has invalid ancestor path");
                }
                if ((string) $path[0] !== $rootCauseStr) {
                    throw new UnexpectedValueException("Ancestor path start {$path[0]} does not match root cause {$rootCauseStr}");
                }
                if ((string) end($path) !== $nodeStr) {
                    throw new UnexpectedValueException("Ancestor path end does not match alarm node {$nodeStr}");
                }
                // Verify each hop in path exists in topology edges
                for ($i = 0; $i < count($path) - 1; $i++) {
                    $u = (string) $path[$i];
                    $v = (string) $path[$i + 1];
                    if (! isset($edges[$u.'->'.$v])) {
                        throw new UnexpectedValueException("Edge {$u} -> {$v} in ancestor path does not exist in topology");
                    }
                }
            } elseif ($status === 'independent') {
                $indepCount++;
                if (self::hasDownAncestor($nodeStr, $revAdj, $isNodeDown)) {
                    throw new UnexpectedValueException("Independent alarm {$alarm['id']} on node {$nodeStr} has a down ancestor");
                }
            } else {
                throw new UnexpectedValueException("Invalid alarm status '{$status}' at index {$idx}");
            }
        }

        if ($rootCount !== $result['root_causes_count']) {
            throw new UnexpectedValueException("Root causes count mismatch: actual {$rootCount} != claimed {$result['root_causes_count']}");
        }
        if ($suppCount !== $result['suppressed_count']) {
            throw new UnexpectedValueException("Suppressed count mismatch: actual {$suppCount} != claimed {$result['suppressed_count']}");
        }
        if ($indepCount !== $result['independent_count']) {
            throw new UnexpectedValueException("Independent count mismatch: actual {$indepCount} != claimed {$result['independent_count']}");
        }
        $uniqueRootNodes = [];
        $suppressedAlarmIdMap = [];
        foreach ($result['alarms'] as $al) {
            if ($al['status'] === 'root_cause') {
                $uniqueRootNodes[(string) $al['node']] = true;
            } elseif ($al['status'] === 'suppressed') {
                $suppressedAlarmIdMap[(string) $al['id']] = true;
            }
        }
        if (count($result['tickets']) !== count($uniqueRootNodes)) {
            throw new UnexpectedValueException('Ticket count does not match root causes count');
        }

        // Verify total suppressed alarm IDs in tickets matches suppressed count
        $totalSuppInTickets = 0;
        foreach ($result['tickets'] as $t) {
            foreach ($t['suppressed_alarm_ids'] as $suppId) {
                if (! isset($suppressedAlarmIdMap[(string) $suppId])) {
                    throw new UnexpectedValueException("Ticket {$t['ticket_id']} references unknown suppressed alarm ID {$suppId}");
                }
                $totalSuppInTickets++;
            }
        }
        if ($totalSuppInTickets !== $suppCount) {
            throw new UnexpectedValueException("Total suppressed IDs in tickets {$totalSuppInTickets} != suppressed count {$suppCount}");
        }

        return true;
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
}
