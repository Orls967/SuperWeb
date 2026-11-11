<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Boundaries;

/**
 * Deterministic blast radius and ripple-effect analyzer for modular dependencies.
 *
 * Computes forward and backward reachability, transitive closure, critical impact paths,
 * and vulnerability risk scores across platform modules.
 */
final class CrossModuleBlastRadiusAnalyzer
{
    /**
     * Compute comprehensive blast radius for a given target module.
     *
     * @param  array<string, list<string>>  $dependencyGraph  Adjacency list: client => [targets]
     * @param  array<string, int>  $moduleCoverageScores  Coverage permille per module (0..1000)
     * @return array{
     *     target: string,
     *     directConsumers: list<string>,
     *     transitiveConsumers: list<string>,
     *     directDependencies: list<string>,
     *     transitiveDependencies: list<string>,
     *     maxRippleDepth: int,
     *     impactScorePermille: int,
     *     criticalPaths: list<list<string>>,
     *     mitigationRecommendations: list<string>
     * }
     */
    public function analyze(
        string $targetModule,
        array $dependencyGraph,
        array $moduleCoverageScores = []
    ): array {
        // 1. Build inverted graph: target => [consumers]
        $invertedGraph = [];
        foreach (array_keys($dependencyGraph) as $mod) {
            $invertedGraph[$mod] = [];
        }

        foreach ($dependencyGraph as $consumer => $providers) {
            foreach ($providers as $provider) {
                if (isset($invertedGraph[$provider])) {
                    $invertedGraph[$provider][] = $consumer;
                }
            }
        }

        foreach ($invertedGraph as &$consumers) {
            sort($consumers, SORT_STRING);
        }
        unset($consumers);

        // 2. Direct consumers (modules that directly depend on $targetModule)
        $directConsumers = $invertedGraph[$targetModule] ?? [];

        // 3. Transitive consumers via Breadth-First Search (BFS) with depth tracking
        $transitiveConsumers = [];
        $consumerDepths = [];
        $queue = [];

        foreach ($directConsumers as $dc) {
            $queue[] = ['module' => $dc, 'depth' => 1, 'path' => [$targetModule, $dc]];
            $consumerDepths[$dc] = 1;
        }

        $criticalPaths = [];
        $maxDepth = 0;

        while ($queue !== []) {
            $current = array_shift($queue);
            $mod = $current['module'];
            $depth = $current['depth'];
            $path = $current['path'];

            if (! in_array($mod, $transitiveConsumers, true)) {
                $transitiveConsumers[] = $mod;
            }

            if ($depth > $maxDepth) {
                $maxDepth = $depth;
            }

            $nextConsumers = $invertedGraph[$mod] ?? [];
            if ($nextConsumers === [] && count($path) >= 2) {
                $criticalPaths[] = $path;
            }

            foreach ($nextConsumers as $nc) {
                if (! isset($consumerDepths[$nc])) {
                    $consumerDepths[$nc] = $depth + 1;
                    $queue[] = [
                        'module' => $nc,
                        'depth' => $depth + 1,
                        'path' => [...$path, $nc],
                    ];
                }
            }
        }

        sort($transitiveConsumers, SORT_STRING);

        // 4. Backward dependencies (modules $targetModule depends on)
        $directDependencies = $dependencyGraph[$targetModule] ?? [];
        sort($directDependencies, SORT_STRING);

        $transitiveDependencies = [];
        $depQueue = $directDependencies;
        $visitedDeps = [];

        while ($depQueue !== []) {
            $dep = array_shift($depQueue);
            if (isset($visitedDeps[$dep])) {
                continue;
            }
            $visitedDeps[$dep] = true;
            $transitiveDependencies[] = $dep;

            foreach ($dependencyGraph[$dep] ?? [] as $nextDep) {
                if (! isset($visitedDeps[$nextDep])) {
                    $depQueue[] = $nextDep;
                }
            }
        }

        sort($transitiveDependencies, SORT_STRING);

        // 5. Calculate Impact Score Permille (0..1000)
        $totalModules = max(1, count($dependencyGraph));
        $reachRatio = (count($transitiveConsumers) * 1000) / $totalModules;
        $depthFactor = min(1000, $maxDepth * 200);

        // Weigh coverage score of the target module (higher coverage = safer change = lower risk)
        $targetCoverage = $moduleCoverageScores[$targetModule] ?? 1000;
        $riskPermille = (int) floor(($reachRatio * 0.6) + ($depthFactor * 0.4));
        $impactScore = (int) floor(($riskPermille * (1000 - ($targetCoverage / 2))) / 1000);
        $impactScore = max(0, min(1000, $impactScore));

        // 6. Generate mitigation recommendations
        $mitigations = [];
        if ($maxDepth > 3) {
            $mitigations[] = "Kedalaman riak tinggi ({$maxDepth} level). Gunakan EventSpine untuk memutus rantai sinkron.";
        }
        if (count($directConsumers) > 10) {
            $mitigations[] = 'Fan-in tinggi ('.count($directConsumers).' konsumen langsung). Stabilkan kontrak di Modules\\Core\\Contracts.';
        }
        if ($targetCoverage < 700) {
            $mitigations[] = "Skor cakupan kontrak rendah ({$targetCoverage} permil). Prioritaskan pembuatan interface kontrak formal.";
        }
        if ($mitigations === []) {
            $mitigations[] = 'Dampak arsitektur terkendali. Perubahan dapat dilakukan dengan aman melalui siklus rilis standar.';
        }

        // Sort critical paths by length descending
        usort($criticalPaths, static fn (array $a, array $b): int => count($b) <=> count($a));
        $criticalPaths = array_slice($criticalPaths, 0, 5);

        return [
            'target' => $targetModule,
            'directConsumers' => $directConsumers,
            'transitiveConsumers' => $transitiveConsumers,
            'directDependencies' => $directDependencies,
            'transitiveDependencies' => $transitiveDependencies,
            'maxRippleDepth' => $maxDepth,
            'impactScorePermille' => $impactScore,
            'criticalPaths' => $criticalPaths,
            'mitigationRecommendations' => $mitigations,
        ];
    }
}
