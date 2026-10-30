<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Math;

use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use InvalidArgumentException;
use Modules\Shared\Domain\Math\Exceptions\CycleDetectedException;

/**
 * Directed Acyclic Graph (DAG) Bill of Materials / Recipe Explosion.
 *
 * Guarantees:
 * - Topological sort with Kahn's algorithm and lexicographical tie-break
 * - Cycle detection returning full cycle path in CycleDetectedException
 * - Exact BigRational arithmetic with edge yield bps and batch yield
 * - Single ceiling operation at total raw ingredient demand
 * - Dual-traversal certificate verification (Kahn vs independent DFS memo) in production (PR2)
 *
 * Complexity:
 * - Time: O(V + E) where V is recipes/ingredients and E is dependency edges
 * - Space: O(V + E) auxiliary space
 */
final class DagExplosion
{
    /**
     * @param  array<string, int|BigRational>  $demands  Node ID => desired output quantity
     * @param array<string, array{
     *     batch_yield?: int|BigRational,
     *     ingredients: list<array{id: string, quantity: int|BigRational, yield_bps?: int}>
     * }> $recipes
     *
     * @throws InvalidArgumentException|CycleDetectedException
     */
    public static function explode(array $demands, array $recipes): DagExplosionResult
    {
        $normalizedDemands = [];
        foreach ($demands as $nodeId => $qty) {
            if ($nodeId === '') {
                throw new InvalidArgumentException('Node ID cannot be empty');
            }
            $rationalQty = self::toRational($qty, "Demand for {$nodeId}");
            if ($rationalQty->isNegative()) {
                throw new InvalidArgumentException("Demand for {$nodeId} cannot be negative: {$qty}");
            }
            $normalizedDemands[$nodeId] = $rationalQty;
        }

        // Validate recipe graph definitions
        $normalizedRecipes = [];
        $adjList = [];
        $inDegree = [];
        $allNodes = [];

        foreach ($demands as $nodeId => $_) {
            $allNodes[$nodeId] = true;
        }

        foreach ($recipes as $nodeId => $def) {
            $allNodes[$nodeId] = true;
            $batchYield = isset($def['batch_yield'])
                ? self::toRational($def['batch_yield'], "Batch yield for {$nodeId}")
                : BigRational::one();

            if ($batchYield->isLessThanOrEqualTo(0)) {
                throw new InvalidArgumentException("Batch yield for {$nodeId} must be positive");
            }

            $ingredients = [];
            foreach ($def['ingredients'] as $ing) {
                $childId = $ing['id'];
                if ($childId === '') {
                    throw new InvalidArgumentException("Ingredient ID cannot be empty in recipe {$nodeId}");
                }
                $allNodes[$childId] = true;

                $childQty = self::toRational($ing['quantity'], "Quantity for {$childId} in {$nodeId}");
                if ($childQty->isLessThanOrEqualTo(0)) {
                    throw new InvalidArgumentException("Quantity for {$childId} in {$nodeId} must be positive");
                }

                $yieldBps = $ing['yield_bps'] ?? 10_000;
                if ($yieldBps <= 0) {
                    throw new InvalidArgumentException("Yield BPS for {$childId} in {$nodeId} must be positive, got: {$yieldBps}");
                }

                $ingredients[] = [
                    'id' => $childId,
                    'quantity' => $childQty,
                    'yield_bps' => $yieldBps,
                ];

                $adjList[$nodeId][] = $childId;
                $inDegree[$childId] = ($inDegree[$childId] ?? 0) + 1;
            }

            $normalizedRecipes[$nodeId] = [
                'batch_yield' => $batchYield,
                'ingredients' => $ingredients,
            ];
        }

        // Cycle detection check
        self::detectCycles(array_keys($allNodes), $adjList);

        // Kahn's algorithm topological order computation
        $kahnTotals = self::runKahnExplosion($normalizedDemands, $normalizedRecipes);

        // Independent DFS memoization traversal
        $dfsTotals = self::runDfsExplosion($normalizedDemands, $normalizedRecipes);

        // Final ceiling once on total
        $ceiledTotals = [];
        foreach ($kahnTotals as $id => $val) {
            $ceiledTotals[$id] = $val->getNumerator()->dividedBy($val->getDenominator(), RoundingMode::Ceiling)->toInt();
        }

        ksort($kahnTotals);
        ksort($dfsTotals);
        ksort($ceiledTotals);

        $cert = new DagExplosionCertificate($kahnTotals, $dfsTotals, $ceiledTotals);
        DagExplosionCertificateVerifier::verify($cert);

        return new DagExplosionResult($kahnTotals, $ceiledTotals, $cert);
    }

    /**
     * @param  list<string>  $allNodes
     * @param  array<string, list<string>>  $adjList
     *
     * @throws CycleDetectedException
     */
    private static function detectCycles(array $allNodes, array $adjList): void
    {
        $visited = [];
        $recursionStack = [];

        sort($allNodes);

        foreach ($allNodes as $node) {
            if (! isset($visited[$node])) {
                $path = [];
                if (self::dfsCycleCheck($node, $adjList, $visited, $recursionStack, $path)) {
                    throw new CycleDetectedException($path);
                }
            }
        }
    }

    /**
     * @param  array<string, list<string>>  $adjList
     * @param  array<string, bool>  $visited
     * @param  array<string, bool>  $recursionStack
     * @param  list<string>  $path
     */
    private static function dfsCycleCheck(
        string $node,
        array $adjList,
        array &$visited,
        array &$recursionStack,
        array &$path
    ): bool {
        $visited[$node] = true;
        $recursionStack[$node] = true;
        $path[] = $node;

        $neighbors = $adjList[$node] ?? [];
        sort($neighbors);

        foreach ($neighbors as $neighbor) {
            if (! isset($visited[$neighbor])) {
                if (self::dfsCycleCheck($neighbor, $adjList, $visited, $recursionStack, $path)) {
                    return true;
                }
            } elseif (! empty($recursionStack[$neighbor])) {
                $path[] = $neighbor;
                // Trim path to start at first occurrence of neighbor
                $startIdx = array_search($neighbor, $path, true);
                if ($startIdx !== false) {
                    $path = array_slice($path, (int) $startIdx);
                }

                return true;
            }
        }

        $recursionStack[$node] = false;
        array_pop($path);

        return false;
    }

    /**
     * @param  array<string, BigRational>  $demands
     * @param array<string, array{
     *     batch_yield: BigRational,
     *     ingredients: list<array{id: string, quantity: BigRational, yield_bps: int}>
     * }> $recipes
     * @return array<string, BigRational>
     */
    private static function runKahnExplosion(array $demands, array $recipes): array
    {
        // Find reachable nodes from demands
        $reachable = [];
        $reachQueue = array_keys($demands);
        while (! empty($reachQueue)) {
            $curr = array_shift($reachQueue);
            if (! isset($reachable[$curr])) {
                $reachable[$curr] = true;
                if (isset($recipes[$curr])) {
                    foreach ($recipes[$curr]['ingredients'] as $ing) {
                        $reachQueue[] = $ing['id'];
                    }
                }
            }
        }

        // Compute in-degrees within reachable subgraph
        $inDegree = [];
        foreach ($reachable as $id => $_) {
            $inDegree[$id] = 0;
        }

        foreach ($reachable as $parent => $_) {
            if (isset($recipes[$parent])) {
                foreach ($recipes[$parent]['ingredients'] as $ing) {
                    $child = $ing['id'];
                    if (isset($reachable[$child])) {
                        $inDegree[$child]++;
                    }
                }
            }
        }

        $nodeDemands = $demands;
        $queue = [];

        // Collect reachable nodes with inDegree 0
        foreach ($inDegree as $id => $deg) {
            if ($deg === 0) {
                $queue[] = $id;
            }
        }
        sort($queue);

        $rawTotals = [];

        while (! empty($queue)) {
            $curr = array_shift($queue);
            $currDemand = $nodeDemands[$curr] ?? BigRational::zero();

            if (! isset($recipes[$curr]) || empty($recipes[$curr]['ingredients'])) {
                // Leaf raw ingredient
                if (! $currDemand->isZero()) {
                    $rawTotals[$curr] = ($rawTotals[$curr] ?? BigRational::zero())->plus($currDemand);
                }
            } else {
                $recipe = $recipes[$curr];
                $batchYield = $recipe['batch_yield'];
                $batchMultiplier = $currDemand->dividedBy($batchYield);

                foreach ($recipe['ingredients'] as $ing) {
                    $childId = $ing['id'];
                    $qty = $ing['quantity'];
                    $yieldBps = $ing['yield_bps'];

                    $yieldMultiplier = BigRational::of('10000')->dividedBy(BigRational::of((string) $yieldBps));
                    $childNeeded = $batchMultiplier->multipliedBy($qty)->multipliedBy($yieldMultiplier);

                    $nodeDemands[$childId] = ($nodeDemands[$childId] ?? BigRational::zero())->plus($childNeeded);

                    $inDegree[$childId]--;
                    if ($inDegree[$childId] === 0) {
                        $queue[] = $childId;
                        sort($queue);
                    }
                }
            }
        }

        return $rawTotals;
    }

    /**
     * Independent recursive DFS traversal with memoization to verify Kahn's results.
     *
     * @param  array<string, BigRational>  $demands
     * @param array<string, array{
     *     batch_yield: BigRational,
     *     ingredients: list<array{id: string, quantity: BigRational, yield_bps: int}>
     * }> $recipes
     * @return array<string, BigRational>
     */
    private static function runDfsExplosion(array $demands, array $recipes): array
    {
        $memo = [];
        $rawTotals = [];

        foreach ($demands as $nodeId => $demand) {
            if ($demand->isZero()) {
                continue;
            }
            $nodeBreakdown = self::dfsExplodeNode($nodeId, $recipes, $memo);
            foreach ($nodeBreakdown as $rawId => $perUnit) {
                $totalForDemand = $demand->multipliedBy($perUnit);
                $rawTotals[$rawId] = ($rawTotals[$rawId] ?? BigRational::zero())->plus($totalForDemand);
            }
        }

        return $rawTotals;
    }

    /**
     * @param array<string, array{
     *     batch_yield: BigRational,
     *     ingredients: list<array{id: string, quantity: BigRational, yield_bps: int}>
     * }> $recipes
     * @param  array<string, array<string, BigRational>>  $memo
     * @return array<string, BigRational>
     */
    private static function dfsExplodeNode(string $nodeId, array $recipes, array &$memo): array
    {
        if (isset($memo[$nodeId])) {
            return $memo[$nodeId];
        }

        if (! isset($recipes[$nodeId]) || empty($recipes[$nodeId]['ingredients'])) {
            return [$nodeId => BigRational::one()];
        }

        $recipe = $recipes[$nodeId];
        $batchYield = $recipe['batch_yield'];
        $result = [];

        foreach ($recipe['ingredients'] as $ing) {
            $childId = $ing['id'];
            $qty = $ing['quantity'];
            $yieldBps = $ing['yield_bps'];

            $yieldMultiplier = BigRational::of('10000')->dividedBy(BigRational::of((string) $yieldBps));
            $childPerUnitOfParent = $qty->multipliedBy($yieldMultiplier)->dividedBy($batchYield);

            $childBreakdown = self::dfsExplodeNode($childId, $recipes, $memo);
            foreach ($childBreakdown as $rawId => $rawPerChild) {
                $rawPerParent = $childPerUnitOfParent->multipliedBy($rawPerChild);
                $result[$rawId] = ($result[$rawId] ?? BigRational::zero())->plus($rawPerParent);
            }
        }

        $memo[$nodeId] = $result;

        return $result;
    }

    private static function toRational(mixed $val, string $context): BigRational
    {
        if ($val instanceof BigRational) {
            return $val;
        }
        if (is_int($val)) {
            return BigRational::of((string) $val);
        }

        throw new InvalidArgumentException("{$context} must be int or BigRational");
    }
}
