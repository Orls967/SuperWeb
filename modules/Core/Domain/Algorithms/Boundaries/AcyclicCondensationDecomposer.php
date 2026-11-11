<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Boundaries;

/**
 * Hierarchical layer decomposition and DAG condensation analyzer (DR4-1, DR4-2).
 *
 * Partitions modules into architectural dependency layers (Layer 0 to Layer K)
 * based on longest distance from foundational kernel sinks.
 */
final class AcyclicCondensationDecomposer
{
    public function __construct(
        private readonly StronglyConnectedComponents $scc = new StronglyConnectedComponents,
    ) {}

    /**
     * Decompose modules into architectural strata / layers.
     *
     * @param  list<string>  $nodes
     * @param  array<string, list<string>>  $adjacencyList
     * @return array{
     *     hasCycles: bool,
     *     cycleComponents: list<list<string>>,
     *     totalLayers: int,
     *     layers: array<int, list<string>>,
     *     moduleToLayerMap: array<string, int>,
     *     layerViolations: list<array{client: string, clientLayer: int, target: string, targetLayer: int, kind: string}>
     * }
     */
    public function decompose(array $nodes, array $adjacencyList): array
    {
        // 1. Run deterministic Tarjan SCC
        $sccResult = $this->scc->compute($nodes, $adjacencyList);

        $cycleComponents = [];
        foreach ($sccResult['components'] as $comp) {
            if (count($comp) > 1) {
                $cycleComponents[] = $comp;
            }
        }

        // 2. Compute layer assignment using topological depth
        // Sink modules (modules that do not depend on any other module) are at Layer 0 (Bottom/Kernel)
        // Modules depending on Layer L are at Layer L + 1
        $moduleToLayer = [];
        foreach ($nodes as $n) {
            $moduleToLayer[$n] = 0;
        }

        // Iterative relaxation of layer heights (Bellman-Ford style relaxation bounded by N passes)
        $numNodes = count($nodes);
        $changed = true;
        $passes = 0;

        while ($changed && $passes < $numNodes) {
            $changed = false;
            $passes++;

            foreach ($nodes as $u) {
                $uLayer = $moduleToLayer[$u];
                foreach ($adjacencyList[$u] ?? [] as $v) {
                    // u depends on v, so u must be in a strictly higher layer than v
                    $vLayer = $moduleToLayer[$v] ?? 0;
                    if ($uLayer <= $vLayer) {
                        $moduleToLayer[$u] = $vLayer + 1;
                        $changed = true;
                    }
                }
            }
        }

        // 3. Group modules by layer
        $layers = [];
        $maxLayer = 0;
        foreach ($moduleToLayer as $mod => $layer) {
            if (! isset($layers[$layer])) {
                $layers[$layer] = [];
            }
            $layers[$layer][] = $mod;
            if ($layer > $maxLayer) {
                $maxLayer = $layer;
            }
        }

        foreach ($layers as &$modList) {
            sort($modList, SORT_STRING);
        }
        unset($modList);
        ksort($layers);

        // 4. Identify illegal layer jumps (e.g. downward calls that bypass intermediate layers, or upward back-edges)
        $layerViolations = [];
        foreach ($nodes as $u) {
            $uL = $moduleToLayer[$u];
            foreach ($adjacencyList[$u] ?? [] as $v) {
                $vL = $moduleToLayer[$v] ?? 0;
                if ($uL <= $vL) {
                    $layerViolations[] = [
                        'client' => $u,
                        'clientLayer' => $uL,
                        'target' => $v,
                        'targetLayer' => $vL,
                        'kind' => 'UPWARD_OR_CIRCULAR_DEPENDENCY',
                    ];
                }
            }
        }

        return [
            'hasCycles' => $sccResult['hasCycles'],
            'cycleComponents' => $cycleComponents,
            'totalLayers' => $maxLayer + 1,
            'layers' => $layers,
            'moduleToLayerMap' => $moduleToLayer,
            'layerViolations' => $layerViolations,
        ];
    }
}
