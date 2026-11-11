<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Boundaries;

/**
 * Deterministic Tarjan's Strongly Connected Components (SCC) algorithm.
 * Identifies cycles in module dependency graphs (R4-ALG-01, W8-D41).
 */
final class StronglyConnectedComponents
{
    private int $index;

    /** @var array<string, int> */
    private array $indices;

    /** @var array<string, int> */
    private array $lowlink;

    /** @var list<string> */
    private array $stack;

    /** @var array<string, bool> */
    private array $onStack;

    /** @var list<list<string>> */
    private array $components;

    /**
     * Compute SCCs deterministically from nodes and adjacency list.
     *
     * @param  list<string>  $nodes  List of module names
     * @param  array<string, list<string>>  $adjacencyList  caller => list of targets
     * @return array{components: list<list<string>>, condensationDag: array<int, list<int>>, hasCycles: bool, cycles: list<list<string>>}
     */
    public function compute(array $nodes, array $adjacencyList): array
    {
        $this->index = 0;
        $this->indices = [];
        $this->lowlink = [];
        $this->stack = [];
        $this->onStack = [];
        $this->components = [];

        // Sort nodes deterministically
        $sortedNodes = $nodes;
        sort($sortedNodes, SORT_STRING);

        // Normalize adjacency list with sorted unique targets
        $normalizedAdj = [];
        foreach ($sortedNodes as $node) {
            $targets = $adjacencyList[$node] ?? [];
            $targets = array_unique($targets);
            sort($targets, SORT_STRING);
            $normalizedAdj[$node] = $targets;
        }

        foreach ($sortedNodes as $node) {
            if (! isset($this->indices[$node])) {
                $this->strongConnect($node, $normalizedAdj);
            }
        }

        // Sort each component's internal elements alphabetically
        foreach ($this->components as &$component) {
            sort($component, SORT_STRING);
        }
        unset($component);

        // Sort components deterministically by their first element
        usort($this->components, static fn (array $a, array $b): int => strcmp($a[0] ?? '', $b[0] ?? ''));

        // Identify cycles: any component with > 1 element or self-loop
        $cycles = [];
        foreach ($this->components as $comp) {
            if (count($comp) > 1) {
                $cycles[] = $comp;
            } elseif (count($comp) === 1) {
                $single = $comp[0];
                if (in_array($single, $normalizedAdj[$single] ?? [], true)) {
                    $cycles[] = $comp;
                }
            }
        }

        // Build Condensation DAG
        $nodeToScc = [];
        foreach ($this->components as $sccId => $comp) {
            foreach ($comp as $module) {
                $nodeToScc[$module] = $sccId;
            }
        }

        $condensationDag = [];
        foreach ($this->components as $sccId => $comp) {
            $condensationDag[$sccId] = [];
        }

        foreach ($normalizedAdj as $u => $targets) {
            $uScc = $nodeToScc[$u] ?? null;
            if ($uScc === null) {
                continue;
            }
            foreach ($targets as $v) {
                $vScc = $nodeToScc[$v] ?? null;
                if ($vScc !== null && $uScc !== $vScc) {
                    if (! in_array($vScc, $condensationDag[$uScc], true)) {
                        $condensationDag[$uScc][] = $vScc;
                    }
                }
            }
        }

        foreach ($condensationDag as $sccId => &$edges) {
            sort($edges, SORT_NUMERIC);
        }
        unset($edges);

        return [
            'components' => $this->components,
            'condensationDag' => $condensationDag,
            'hasCycles' => count($cycles) > 0,
            'cycles' => $cycles,
        ];
    }

    /**
     * @param  array<string, list<string>>  $adj
     */
    private function strongConnect(string $v, array $adj): void
    {
        $this->indices[$v] = $this->index;
        $this->lowlink[$v] = $this->index;
        $this->index++;
        $this->stack[] = $v;
        $this->onStack[$v] = true;

        foreach ($adj[$v] ?? [] as $w) {
            if (! isset($this->indices[$w])) {
                $this->strongConnect($w, $adj);
                $this->lowlink[$v] = min($this->lowlink[$v], $this->lowlink[$w]);
            } elseif ($this->onStack[$w] ?? false) {
                $this->lowlink[$v] = min($this->lowlink[$v], $this->indices[$w]);
            }
        }

        if ($this->lowlink[$v] === $this->indices[$v]) {
            $component = [];
            do {
                $w = array_pop($this->stack);
                $this->onStack[$w] = false;
                $component[] = $w;
            } while ($w !== $v && count($this->stack) >= 0);

            $this->components[] = $component;
        }
    }
}
