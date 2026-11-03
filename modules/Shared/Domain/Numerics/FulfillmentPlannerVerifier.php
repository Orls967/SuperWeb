<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Numerics;

use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Verifier for Multi-Node Fulfillment Split Optimizer results.
 *
 * Verifies:
 * 1. Feasibility: all order lines are fully satisfied by allocations without exceeding node stocks.
 * 2. Cost & Shipment accounting: total_shipments and total_cost match selected nodes.
 * 3. Infeasibility proof: if marked INFEASIBLE, proves that combined stock across all nodes is insufficient.
 * 4. Optimality proof (for exact algorithm): independent re-enumeration proves no strictly better subset exists.
 * 5. Certificate consistency.
 */
final class FulfillmentPlannerVerifier
{
    /**
     * @param array{
     *     status: string,
     *     mode: string,
     *     algorithm: string,
     *     total_shipments: int,
     *     total_cost: int,
     *     selected_nodes: list<string>,
     *     allocations: array<string, array<string, int>>,
     *     unfulfillable_lines: array<string, int>,
     *     certificate?: array<string, mixed>
     * } $result
     * @param  array<string|int, int>|list<array{item: string|int, quantity: int}>  $orderLines
     * @param  array<string|int, array{cost: int, stock: array<string|int, int>}>|list<array{id: string|int, cost: int, stock: array<string|int, int>}>  $nodes
     */
    public static function verify(array $result, array $orderLines, array $nodes, string $mode = FulfillmentPlanner::MODE_LEXICOGRAPHIC): bool
    {
        if (! isset($result['status'], $result['mode'], $result['algorithm'], $result['total_shipments'], $result['total_cost'], $result['selected_nodes'], $result['allocations'], $result['unfulfillable_lines'])) {
            throw new InvalidArgumentException('Result array missing required fulfillment keys.');
        }

        // Normalize lines
        $lines = [];
        foreach ($orderLines as $key => $val) {
            if (is_array($val) && isset($val['item'], $val['quantity'])) {
                $item = (string) $val['item'];
                $qty = (int) $val['quantity'];
            } else {
                $item = (string) $key;
                $qty = (int) $val;
            }
            $lines[$item] = ($lines[$item] ?? 0) + $qty;
        }

        // Normalize nodes
        $nodeData = [];
        foreach ($nodes as $key => $val) {
            if (is_array($val) && isset($val['id'], $val['cost'], $val['stock'])) {
                $nodeId = (string) $val['id'];
                $cost = (int) $val['cost'];
                $rawStock = $val['stock'];
            } else {
                $nodeId = (string) $key;
                $cost = (int) $val['cost'];
                $rawStock = $val['stock'];
            }

            $stock = [];
            foreach ($rawStock as $item => $sqty) {
                $stock[(string) $item] = (int) $sqty;
            }

            $nodeData[$nodeId] = [
                'cost' => $cost,
                'stock' => $stock,
            ];
        }

        // 1. INFEASIBLE case
        if ($result['status'] === 'INFEASIBLE') {
            if (! empty($result['selected_nodes']) || ! empty($result['allocations'])) {
                throw new UnexpectedValueException('Infeasible result must have empty selected_nodes and allocations.');
            }
            if ($result['total_shipments'] !== 0 || $result['total_cost'] !== 0) {
                throw new UnexpectedValueException('Infeasible result must have 0 total_shipments and 0 total_cost.');
            }
            if (empty($result['unfulfillable_lines'])) {
                throw new UnexpectedValueException('Infeasible result must specify unfulfillable_lines.');
            }

            // Verify that total stock is indeed insufficient
            $allCovered = true;
            foreach ($lines as $item => $req) {
                $tot = 0;
                foreach ($nodeData as $n) {
                    $tot += $n['stock'][$item] ?? 0;
                }
                if ($tot < $req) {
                    $allCovered = false;
                    break;
                }
            }
            if ($allCovered) {
                throw new UnexpectedValueException('Result marked INFEASIBLE but combined stock across all nodes is sufficient.');
            }

            self::verifyCertificate($result);

            return true;
        }

        // 2. OPTIMAL case
        if ($result['status'] !== 'OPTIMAL') {
            throw new UnexpectedValueException("Invalid result status: '{$result['status']}'");
        }

        if (! empty($result['unfulfillable_lines'])) {
            throw new UnexpectedValueException('Optimal result must not have unfulfillable_lines.');
        }

        $selectedNodes = array_map('strval', $result['selected_nodes']);
        if (count($selectedNodes) !== $result['total_shipments']) {
            throw new UnexpectedValueException('total_shipments mismatch: expected '.count($selectedNodes).", got {$result['total_shipments']}.");
        }

        // Verify total cost
        $computedCost = 0;
        foreach ($selectedNodes as $nid) {
            if (! isset($nodeData[$nid])) {
                throw new UnexpectedValueException("Selected node '{$nid}' does not exist in node registry.");
            }
            $computedCost += $nodeData[$nid]['cost'];
        }
        if ($computedCost !== $result['total_cost']) {
            throw new UnexpectedValueException("total_cost mismatch: expected {$computedCost}, got {$result['total_cost']}.");
        }

        // Verify line satisfaction and stock limits
        $allocatedPerItem = [];
        foreach ($selectedNodes as $nid) {
            if (! array_key_exists($nid, $result['allocations'])) {
                throw new UnexpectedValueException("Missing allocation entry for selected node '{$nid}'.");
            }
            $nodeAlloc = $result['allocations'][$nid];
            foreach ($nodeAlloc as $item => $qty) {
                $itemKey = (string) $item;
                $allocQty = (int) $qty;
                if ($allocQty <= 0) {
                    throw new UnexpectedValueException("Allocation for item '{$itemKey}' at node '{$nid}' must be positive, got: {$allocQty}");
                }
                $maxAvail = $nodeData[$nid]['stock'][$itemKey] ?? 0;
                if ($allocQty > $maxAvail) {
                    throw new UnexpectedValueException("Allocation for item '{$itemKey}' at node '{$nid}' ({$allocQty}) exceeds node stock ({$maxAvail}).");
                }
                $allocatedPerItem[$itemKey] = ($allocatedPerItem[$itemKey] ?? 0) + $allocQty;
            }
        }

        foreach ($lines as $item => $req) {
            $totalAlloc = $allocatedPerItem[$item] ?? 0;
            if ($totalAlloc !== $req) {
                throw new UnexpectedValueException("Order line for item '{$item}' not fully satisfied: needed {$req}, allocated {$totalAlloc}.");
            }
        }

        // 3. Optimality verification for exact algorithm
        if ($result['algorithm'] === 'exact' && count($nodeData) <= FulfillmentPlanner::MAX_EXACT_NODES && count($lines) <= FulfillmentPlanner::MAX_EXACT_LINES) {
            self::verifyExactOptimality($result, $lines, $nodeData, $mode);
        }

        self::verifyCertificate($result);

        return true;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, int>  $lines
     * @param  array<string, array{cost: int, stock: array<string, int>}>  $nodeData
     */
    private static function verifyExactOptimality(array $result, array $lines, array $nodeData, string $mode): void
    {
        $nodeIds = array_keys($nodeData);
        $n = count($nodeIds);
        $claimedShipments = $result['total_shipments'];
        $claimedCost = $result['total_cost'];

        for ($k = 1; $k <= $n; $k++) {
            $combinations = FulfillmentPlanner::combinations($nodeIds, $k);
            foreach ($combinations as $subset) {
                if (FulfillmentPlanner::covers($lines, $subset, $nodeData)) {
                    $subsetCost = 0;
                    foreach ($subset as $nid) {
                        $subsetCost += $nodeData[$nid]['cost'];
                    }

                    if ($mode === FulfillmentPlanner::MODE_LEXICOGRAPHIC) {
                        // Smaller shipment count found!
                        if ($k < $claimedShipments) {
                            throw new UnexpectedValueException("Suboptimal plan detected: valid cover of size {$k} exists (claimed {$claimedShipments}).");
                        }
                        // Same shipment count but cheaper cost found!
                        if ($k === $claimedShipments && $subsetCost < $claimedCost) {
                            throw new UnexpectedValueException("Suboptimal plan detected: valid cover of size {$k} with lower cost {$subsetCost} exists (claimed {$claimedCost}).");
                        }
                    } else {
                        // Cost mode: cheaper cost found!
                        if ($subsetCost < $claimedCost) {
                            throw new UnexpectedValueException("Suboptimal plan detected: valid cover with lower cost {$subsetCost} exists (claimed {$claimedCost}).");
                        }
                        if ($subsetCost === $claimedCost && $k < $claimedShipments) {
                            throw new UnexpectedValueException("Suboptimal plan detected: valid cover with same cost {$subsetCost} but fewer shipments {$k} exists (claimed {$claimedShipments}).");
                        }
                    }
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private static function verifyCertificate(array $result): void
    {
        if (! isset($result['certificate'])) {
            return;
        }

        $cert = $result['certificate'];
        if (($cert['status'] ?? null) !== $result['status']) {
            throw new UnexpectedValueException('Certificate status mismatch.');
        }
        if (($cert['total_shipments'] ?? null) !== $result['total_shipments']) {
            throw new UnexpectedValueException('Certificate total_shipments mismatch.');
        }
        if (($cert['total_cost'] ?? null) !== $result['total_cost']) {
            throw new UnexpectedValueException('Certificate total_cost mismatch.');
        }
        if (($cert['selected_nodes'] ?? null) !== $result['selected_nodes']) {
            throw new UnexpectedValueException('Certificate selected_nodes mismatch.');
        }
        if (($cert['unfulfillable_lines'] ?? null) !== $result['unfulfillable_lines']) {
            throw new UnexpectedValueException('Certificate unfulfillable_lines mismatch.');
        }
    }
}
