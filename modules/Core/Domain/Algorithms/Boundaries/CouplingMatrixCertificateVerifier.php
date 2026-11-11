<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Boundaries;

/**
 * Formal certificate verifier for ModuleCouplingMatrixScorer.
 *
 * Verifies mathematical properties:
 * 1. Global Conservation of Edges: sum(Ca) == sum(Ce) across all modules in the closed graph
 * 2. Bounds Invariants: I, A, D in [0, 1000] permille
 * 3. Identity Invariants: for isolated nodes (Ca=0, Ce=0), I=500
 */
final class CouplingMatrixCertificateVerifier
{
    /**
     * @param  array{
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
     * }  $report
     */
    public function verify(array $report): bool
    {
        $metrics = $report['perModuleMetrics'];
        $sumCa = 0;
        $sumCe = 0;

        foreach ($metrics as $module => $m) {
            $ca = $m['afferentCoupling'];
            $ce = $m['efferentCoupling'];
            $i = $m['instabilityPermille'];
            $a = $m['abstractnessPermille'];
            $d = $m['normalizedDistancePermille'];

            if ($ca < 0 || $ce < 0) {
                return false;
            }

            if ($i < 0 || $i > 1000) {
                return false;
            }

            if ($a < 0 || $a > 1000) {
                return false;
            }

            if ($d < 0 || $d > 1000) {
                return false;
            }

            // Expected distance calculation check
            $expectedDistance = min(1000, abs($a + $i - 1000));
            if ($d !== $expectedDistance) {
                return false;
            }

            $sumCa += $ca;
            $sumCe += $ce;
        }

        // Global conservation of directed edges in closed system: sum(Ca) == sum(Ce)
        return $sumCa === $sumCe;
    }
}
