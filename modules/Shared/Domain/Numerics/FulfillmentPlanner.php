<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Numerics;

use InvalidArgumentException;

/**
 * Multi-Node Fulfillment Split Optimizer.
 *
 * Optimizes order fulfillment across distribution nodes with inventory stock and shipping costs.
 *
 * Modes:
 * - 'lexicographic': Minimizes shipment count first, then total shipping cost (tie-break node ID).
 * - 'cost': Minimizes total shipping cost first, then shipment count (tie-break node ID).
 *
 * For <= 10 nodes and <= 20 order lines: Exact subset enumeration with guaranteed global optimality.
 * For > 10 nodes or > 20 lines: Greedy heuristic marked with algorithm => 'heuristic'.
 *
 * Complexity: O(2^N * L) for exact enumeration where N <= 10 and L <= 20.
 */
final class FulfillmentPlanner
{
    public const MODE_LEXICOGRAPHIC = 'lexicographic';

    public const MODE_COST = 'cost';

    public const MAX_EXACT_NODES = 10;

    public const MAX_EXACT_LINES = 20;

    /**
     * @param  array<string|int, int>|list<array{item: string|int, quantity: int}>  $orderLines
     * @param  array<string|int, array{cost: int, stock: array<string|int, int>}>|list<array{id: string|int, cost: int, stock: array<string|int, int>}>  $nodes
     * @param  string  $mode  'lexicographic'|'cost'
     * @return array{
     *     status: 'OPTIMAL'|'INFEASIBLE',
     *     mode: string,
     *     algorithm: 'exact'|'heuristic',
     *     total_shipments: int,
     *     total_cost: int,
     *     selected_nodes: list<string>,
     *     allocations: array<string, array<string, int>>,
     *     unfulfillable_lines: array<string, int>,
     *     certificate: array{
     *         status: string,
     *         mode: string,
     *         algorithm: string,
     *         total_shipments: int,
     *         total_cost: int,
     *         selected_nodes: list<string>,
     *         allocations: array<string, array<string, int>>,
     *         unfulfillable_lines: array<string, int>
     *     }
     * }
     */
    public static function plan(array $orderLines, array $nodes, string $mode = self::MODE_LEXICOGRAPHIC): array
    {
        if ($mode !== self::MODE_LEXICOGRAPHIC && $mode !== self::MODE_COST) {
            throw new InvalidArgumentException("Invalid optimization mode: '{$mode}'. Must be 'lexicographic' or 'cost'.");
        }

        // Normalize order lines: item_id => required_qty
        $lines = [];
        foreach ($orderLines as $key => $val) {
            if (is_array($val) && isset($val['item'], $val['quantity'])) {
                $item = (string) $val['item'];
                $qty = (int) $val['quantity'];
            } elseif (is_numeric($val) || is_int($val)) {
                $item = (string) $key;
                $qty = (int) $val;
            } else {
                throw new InvalidArgumentException("Invalid order line format near '{$key}'");
            }

            if ($qty <= 0) {
                throw new InvalidArgumentException("Quantity for item '{$item}' must be positive, got: {$qty}");
            }
            $lines[$item] = ($lines[$item] ?? 0) + $qty;
        }

        if (empty($lines)) {
            throw new InvalidArgumentException('Order lines cannot be empty.');
        }

        // Normalize nodes: nodeId => ['cost' => int, 'stock' => [item => int]]
        $nodeData = [];
        foreach ($nodes as $key => $val) {
            if (is_array($val) && isset($val['id'], $val['cost'], $val['stock'])) {
                $nodeId = (string) $val['id'];
                $cost = (int) $val['cost'];
                $rawStock = $val['stock'];
            } elseif (is_array($val) && isset($val['cost'], $val['stock'])) {
                $nodeId = (string) $key;
                $cost = (int) $val['cost'];
                $rawStock = $val['stock'];
            } else {
                throw new InvalidArgumentException("Invalid node format near '{$key}'");
            }

            if ($cost < 0) {
                throw new InvalidArgumentException("Node '{$nodeId}' shipping cost must be non-negative, got: {$cost}");
            }

            $stock = [];
            foreach ($rawStock as $item => $sqty) {
                $stockQty = (int) $sqty;
                if ($stockQty < 0) {
                    throw new InvalidArgumentException("Node '{$nodeId}' item '{$item}' stock cannot be negative.");
                }
                if ($stockQty > 0) {
                    $stock[(string) $item] = $stockQty;
                }
            }

            $nodeData[$nodeId] = [
                'cost' => $cost,
                'stock' => $stock,
            ];
        }

        // Check overall feasibility across all nodes combined
        $unfulfillable = [];
        foreach ($lines as $item => $needed) {
            $totalAvail = 0;
            foreach ($nodeData as $nData) {
                $totalAvail += $nData['stock'][$item] ?? 0;
            }
            if ($totalAvail < $needed) {
                $unfulfillable[$item] = $needed - $totalAvail;
            }
        }

        $nodeCount = count($nodeData);
        $lineCount = count($lines);
        $algorithm = ($nodeCount <= self::MAX_EXACT_NODES && $lineCount <= self::MAX_EXACT_LINES)
            ? 'exact'
            : 'heuristic';

        if (! empty($unfulfillable)) {
            $certificate = [
                'status' => 'INFEASIBLE',
                'mode' => $mode,
                'algorithm' => $algorithm,
                'total_shipments' => 0,
                'total_cost' => 0,
                'selected_nodes' => [],
                'allocations' => [],
                'unfulfillable_lines' => $unfulfillable,
            ];

            $result = [
                'status' => 'INFEASIBLE',
                'mode' => $mode,
                'algorithm' => $algorithm,
                'total_shipments' => 0,
                'total_cost' => 0,
                'selected_nodes' => [],
                'allocations' => [],
                'unfulfillable_lines' => $unfulfillable,
                'certificate' => $certificate,
            ];

            FulfillmentPlannerVerifier::verify($result, $lines, $nodeData, $mode);

            return $result;
        }

        // Solve: exact enumeration or greedy heuristic
        if ($algorithm === 'exact') {
            $selectedNodes = self::solveExact($lines, $nodeData, $mode);
        } else {
            $selectedNodes = self::solveGreedy($lines, $nodeData, $mode);
        }

        sort($selectedNodes);

        // Build deterministic allocation of items to selected nodes
        $allocations = self::buildAllocations($lines, $selectedNodes, $nodeData);

        $totalCost = 0;
        foreach ($selectedNodes as $nid) {
            $totalCost += $nodeData[$nid]['cost'];
        }

        $certificate = [
            'status' => 'OPTIMAL',
            'mode' => $mode,
            'algorithm' => $algorithm,
            'total_shipments' => count($selectedNodes),
            'total_cost' => $totalCost,
            'selected_nodes' => $selectedNodes,
            'allocations' => $allocations,
            'unfulfillable_lines' => [],
        ];

        $result = [
            'status' => 'OPTIMAL',
            'mode' => $mode,
            'algorithm' => $algorithm,
            'total_shipments' => count($selectedNodes),
            'total_cost' => $totalCost,
            'selected_nodes' => $selectedNodes,
            'allocations' => $allocations,
            'unfulfillable_lines' => [],
            'certificate' => $certificate,
        ];

        FulfillmentPlannerVerifier::verify($result, $lines, $nodeData, $mode);

        return $result;
    }

    /**
     * Exact enumeration over subset of nodes.
     *
     * @param  array<string, int>  $lines
     * @param  array<string, array{cost: int, stock: array<string, int>}>  $nodeData
     * @return list<string>
     */
    private static function solveExact(array $lines, array $nodeData, string $mode): array
    {
        $nodeIds = array_keys($nodeData);
        sort($nodeIds);
        $n = count($nodeIds);

        $bestSubset = null;
        $bestShipments = PHP_INT_MAX;
        $bestCost = PHP_INT_MAX;

        if ($mode === self::MODE_LEXICOGRAPHIC) {
            for ($k = 1; $k <= $n; $k++) {
                $combinations = self::combinations($nodeIds, $k);
                $foundValidForK = false;

                foreach ($combinations as $subset) {
                    if (self::covers($lines, $subset, $nodeData)) {
                        $foundValidForK = true;
                        $cost = 0;
                        foreach ($subset as $nid) {
                            $cost += $nodeData[$nid]['cost'];
                        }

                        if ($cost < $bestCost) {
                            $bestCost = $cost;
                            $bestSubset = $subset;
                        }
                    }
                }

                if ($foundValidForK) {
                    return $bestSubset;
                }
            }
        } else {
            // Mode: COST
            for ($k = 1; $k <= $n; $k++) {
                $combinations = self::combinations($nodeIds, $k);
                foreach ($combinations as $subset) {
                    if (self::covers($lines, $subset, $nodeData)) {
                        $cost = 0;
                        foreach ($subset as $nid) {
                            $cost += $nodeData[$nid]['cost'];
                        }

                        if ($cost < $bestCost || ($cost === $bestCost && $k < $bestShipments)) {
                            $bestCost = $cost;
                            $bestShipments = $k;
                            $bestSubset = $subset;
                        }
                    }
                }
            }
        }

        return $bestSubset ?? $nodeIds;
    }

    /**
     * Greedy heuristic for larger instances (> 10 nodes or > 20 lines).
     *
     * @param  array<string, int>  $lines
     * @param  array<string, array{cost: int, stock: array<string, int>}>  $nodeData
     * @return list<string>
     */
    private static function solveGreedy(array $lines, array $nodeData, string $mode): array
    {
        $remaining = $lines;
        $selected = [];
        $availableNodeIds = array_keys($nodeData);
        sort($availableNodeIds);

        while (array_sum($remaining) > 0) {
            $bestNodeId = null;
            $bestCoverUnits = 0;
            $bestCost = PHP_INT_MAX;

            foreach ($availableNodeIds as $nid) {
                if (in_array($nid, $selected, true)) {
                    continue;
                }

                $nStock = $nodeData[$nid]['stock'];
                $coveredUnits = 0;
                foreach ($remaining as $item => $req) {
                    if ($req > 0 && isset($nStock[$item])) {
                        $coveredUnits += min($req, $nStock[$item]);
                    }
                }

                if ($coveredUnits <= 0) {
                    continue;
                }

                $nodeCost = max(1, $nodeData[$nid]['cost']);

                if ($mode === self::MODE_LEXICOGRAPHIC) {
                    if ($coveredUnits > $bestCoverUnits || ($coveredUnits === $bestCoverUnits && $nodeData[$nid]['cost'] < $bestCost)) {
                        $bestCoverUnits = $coveredUnits;
                        $bestCost = $nodeData[$nid]['cost'];
                        $bestNodeId = $nid;
                    }
                } else {
                    // Integer cross-multiplication: coveredUnits / nodeCost vs bestCoverUnits / bestCost
                    $cmp = ($coveredUnits * $bestCost) <=> ($bestCoverUnits * $nodeCost);
                    if ($cmp > 0 || ($cmp === 0 && $nodeData[$nid]['cost'] < $bestCost)) {
                        $bestCoverUnits = $coveredUnits;
                        $bestCost = $nodeData[$nid]['cost'];
                        $bestNodeId = $nid;
                    }
                }
            }

            if ($bestNodeId === null) {
                return $selected;
            }

            $selected[] = $bestNodeId;
            foreach ($nodeData[$bestNodeId]['stock'] as $item => $sqty) {
                if (isset($remaining[$item])) {
                    $remaining[$item] = max(0, $remaining[$item] - $sqty);
                }
            }
        }

        return $selected;
    }

    /**
     * @param  array<string, int>  $lines
     * @param  list<string>  $subset
     * @param  array<string, array{cost: int, stock: array<string, int>}>  $nodeData
     */
    public static function covers(array $lines, array $subset, array $nodeData): bool
    {
        foreach ($lines as $item => $req) {
            $totalAvail = 0;
            foreach ($subset as $nid) {
                $totalAvail += $nodeData[$nid]['stock'][$item] ?? 0;
            }
            if ($totalAvail < $req) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $items
     * @return list<list<string>>
     */
    public static function combinations(array $items, int $k): array
    {
        $count = count($items);
        if ($k === 0) {
            return [[]];
        }
        if ($k > $count) {
            return [];
        }
        if ($k === $count) {
            return [$items];
        }

        $result = [];
        $head = $items[0];
        $tail = array_slice($items, 1);

        foreach (self::combinations($tail, $k - 1) as $sub) {
            $sub[] = $head;
            $result[] = $sub;
        }

        foreach (self::combinations($tail, $k) as $sub) {
            $result[] = $sub;
        }

        return $result;
    }

    /**
     * @param  array<string, int>  $lines
     * @param  list<string>  $selectedNodes
     * @param  array<string, array{cost: int, stock: array<string, int>}>  $nodeData
     * @return array<string, array<string, int>>
     */
    private static function buildAllocations(array $lines, array $selectedNodes, array $nodeData): array
    {
        $allocations = [];
        foreach ($selectedNodes as $nid) {
            $allocations[$nid] = [];
        }

        foreach ($lines as $item => $needed) {
            $rem = $needed;
            foreach ($selectedNodes as $nid) {
                $avail = $nodeData[$nid]['stock'][$item] ?? 0;
                if ($rem > 0 && $avail > 0) {
                    $take = min($rem, $avail);
                    $allocations[$nid][$item] = $take;
                    $rem -= $take;
                }
            }
        }

        return $allocations;
    }
}
