<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Boundaries;

/**
 * Deterministic Package Principles and Coupling Matrix Scorer (Martin, 1994).
 *
 * Computes:
 * - Afferent Coupling (Ca): Number of external modules that depend on this module
 * - Efferent Coupling (Ce): Number of external modules this module depends on
 * - Instability (I): Ce / (Ca + Ce) scaled to permille [0..1000]
 * - Abstractness (A): Ratio of interfaces/contracts to total classes in permille [0..1000]
 * - Distance from Main Sequence (D): |A + I - 1000| / sqrt(2) normalized to permille [0..1000]
 * - Zone of Pain vs Zone of Uselessness classification
 */
final class ModuleCouplingMatrixScorer
{
    public function __construct(
        private readonly CouplingMatrixCertificateVerifier $verifier = new CouplingMatrixCertificateVerifier,
    ) {}

    /**
     * Compute coupling metrics across all registered modules.
     *
     * @param  list<string>  $nodes  List of module names
     * @param  array<string, list<string>>  $dependencyGraph  Adjacency list: client => [targets]
     * @param  array<string, array{totalClasses: int, interfaceCount: int}>  $moduleClassCounts  Class/interface counts
     * @return array{
     *     perModuleMetrics: array<string, array{
     *         afferentCoupling: int,
     *         efferentCoupling: int,
     *         instabilityPermille: int,
     *         abstractnessPermille: int,
     *         normalizedDistancePermille: int,
     *         zoneClassification: string
     *     }>,
     *     summary: array{
     *         averageInstabilityPermille: int,
     *         averageDistancePermille: int,
     *         painZoneModules: list<string>,
     *         uselessZoneModules: list<string>,
     *         balancedModules: list<string>
     *     }
     * }
     */
    public function compute(
        array $nodes,
        array $dependencyGraph,
        array $moduleClassCounts = []
    ): array {
        // 1. Calculate Ca (Afferent) and Ce (Efferent) for each module
        $ca = [];
        $ce = [];

        foreach ($nodes as $node) {
            $ca[$node] = 0;
            $ce[$node] = count($dependencyGraph[$node] ?? []);
        }

        foreach ($nodes as $consumer) {
            foreach ($dependencyGraph[$consumer] ?? [] as $provider) {
                if (isset($ca[$provider])) {
                    $ca[$provider]++;
                }
            }
        }

        $perModuleMetrics = [];
        $totalInstability = 0;
        $totalDistance = 0;
        $painZone = [];
        $uselessZone = [];
        $balanced = [];

        foreach ($nodes as $module) {
            $aff = $ca[$module];
            $eff = $ce[$module];
            $totalCoupling = $aff + $eff;

            // Instability I = Ce / (Ca + Ce)
            $instability = $totalCoupling > 0
                ? (int) floor(($eff * 1000) / $totalCoupling)
                : 500; // Balanced default if isolated

            // Abstractness A = Interfaces / Total Classes
            $counts = $moduleClassCounts[$module] ?? ['totalClasses' => 20, 'interfaceCount' => 2];
            $totalClasses = max(1, $counts['totalClasses']);
            $interfaceCount = min($totalClasses, $counts['interfaceCount']);
            $abstractness = (int) floor(($interfaceCount * 1000) / $totalClasses);

            // Normalized Distance from Main Sequence: D = |A + I - 1000|
            $rawDistance = abs($abstractness + $instability - 1000);
            $distance = min(1000, $rawDistance);

            // Zone classification
            // Zone of Pain: Highly concrete (A ~ 0) and highly stable (I ~ 0), hard to extend but many dependents
            // Zone of Uselessness: Highly abstract (A ~ 1000) and highly unstable (I ~ 1000), no dependents
            if ($abstractness < 300 && $instability < 300 && $aff >= 3) {
                $zone = 'ZONE_OF_PAIN';
                $painZone[] = $module;
            } elseif ($abstractness > 700 && $instability > 700 && $aff === 0) {
                $zone = 'ZONE_OF_USELESSNESS';
                $uselessZone[] = $module;
            } else {
                $zone = 'BALANCED_MAIN_SEQUENCE';
                $balanced[] = $module;
            }

            $perModuleMetrics[$module] = [
                'afferentCoupling' => $aff,
                'efferentCoupling' => $eff,
                'instabilityPermille' => $instability,
                'abstractnessPermille' => $abstractness,
                'normalizedDistancePermille' => $distance,
                'zoneClassification' => $zone,
            ];

            $totalInstability += $instability;
            $totalDistance += $distance;
        }

        $count = max(1, count($nodes));
        $avgInstability = (int) floor($totalInstability / $count);
        $avgDistance = (int) floor($totalDistance / $count);

        sort($painZone, SORT_STRING);
        sort($uselessZone, SORT_STRING);
        sort($balanced, SORT_STRING);

        $report = [
            'perModuleMetrics' => $perModuleMetrics,
            'summary' => [
                'averageInstabilityPermille' => $avgInstability,
                'averageDistancePermille' => $avgDistance,
                'painZoneModules' => $painZone,
                'uselessZoneModules' => $uselessZone,
                'balancedModules' => $balanced,
            ],
        ];

        $this->verifier->verify($report);

        return $report;
    }
}

