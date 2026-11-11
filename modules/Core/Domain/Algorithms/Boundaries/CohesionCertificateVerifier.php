<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Boundaries;

/**
 * Formal certificate verifier for ModuleCohesionScorer.
 *
 * Verifies:
 * 1. Numerical bounds: Cohesion score strictly within [0, 5000] permille
 * 2. Deterministic formula: H == min(5000, floor((R + 1) * 1000 / N))
 * 3. Qualitative mapping: CohesionLevel string correctly matches score bracket
 */
final class CohesionCertificateVerifier
{
    /**
     * @param  array{
     *     perModuleCohesion: array<string, array{
     *         totalClasses: int,
     *         internalRelations: int,
     *         cohesionPermille: int,
     *         cohesionLevel: string
     *     }>,
     *     averageCohesionPermille: int,
     *     exemplaryModules: list<string>,
     *     fragmentedModules: list<string>
     * }  $report
     */
    public function verify(array $report): bool
    {
        $modules = $report['perModuleCohesion'];
        $sumCohesion = 0;

        foreach ($modules as $module => $m) {
            $n = $m['totalClasses'];
            $r = $m['internalRelations'];
            $h = $m['cohesionPermille'];
            $level = $m['cohesionLevel'];

            if ($n < 1 || $r < 0) {
                return false;
            }

            if ($h < 0 || $h > 5000) {
                return false;
            }

            $expectedH = min(5000, (int) floor((($r + 1) * 1000) / $n));
            if ($h !== $expectedH) {
                return false;
            }

            $expectedLevel = match (true) {
                $h >= 1500 => 'HIGHLY_COHESIVE',
                $h >= 800 => 'ADEQUATELY_COHESIVE',
                default => 'FRAGMENTED_LOW_COHESION',
            };

            if ($level !== $expectedLevel) {
                return false;
            }

            $sumCohesion += $h;
        }

        $count = max(1, count($modules));
        $expectedAvg = (int) floor($sumCohesion / $count);

        return $report['averageCohesionPermille'] === $expectedAvg;
    }
}

