<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Boundaries;

/**
 * Formal certificate verifier for Contract Coverage Scorer.
 * Proves bounded permille range [0, 1000] and mathematical invariant consistency (R4-ALG-03).
 */
final class CoverageCertificateVerifier
{
    /**
     * @param  array{perModuleScores: array<string, int>, pairScores: array<string, int>, globalScore: int, totalContracts: int, totalViolations: int}  $scoreResult
     */
    public function verify(array $scoreResult): bool
    {
        // 1. Verify global score bounds
        $global = $scoreResult['globalScore'];
        if ($global < 0 || $global > 1000) {
            return false;
        }

        // 2. Verify per-module scores bounds
        foreach ($scoreResult['perModuleScores'] as $score) {
            if ($score < 0 || $score > 1000) {
                return false;
            }
        }

        // 3. Verify pairwise scores bounds
        foreach ($scoreResult['pairScores'] as $score) {
            if ($score < 0 || $score > 1000) {
                return false;
            }
        }

        // 4. Verify mathematical consistency of global score
        $totalC = $scoreResult['totalContracts'];
        $totalV = $scoreResult['totalViolations'];

        if ($totalC < 0 || $totalV < 0) {
            return false;
        }

        $sum = $totalC + $totalV;
        $expectedGlobal = $sum === 0 ? 1000 : (int) intdiv($totalC * 1000, $sum);

        return $global === $expectedGlobal;
    }
}
