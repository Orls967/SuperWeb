<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Boundaries;

use InvalidArgumentException;
use Modules\Core\Domain\Algorithms\CanonicalCertificateSupport;

/**
 * Formal certificate verifier for Contract Coverage Scorer.
 * Complexity: O(M + V) on modules M and violations V. Pure integer arithmetic (permille).
 * Proves bounded permille range [0, 1000], mathematical invariant consistency,
 * and SHA-256 canonical certificate integrity (R4-ALG-03).
 */
final class CoverageCertificateVerifier
{
    /**
     * Build canonical SHA-256 certificate for contract coverage computation.
     *
     * @param  array<string, mixed>  $input
     * @param  list<string>  $steps
     * @param  array<string, mixed>  $result
     * @return array{input: array<string, mixed>, steps: list<string>, result: array<string, mixed>, sha256: string}
     */
    public function issueCertificate(array $input, array $steps, array $result): array
    {
        return CanonicalCertificateSupport::issue($input, $steps, $result);
    }

    /**
     * Verify canonical SHA-256 certificate for coverage scoring; throws on tampering.
     *
     * @param  array<string, mixed>  $certificate
     */
    public function verifyCertificate(array $certificate): bool
    {
        $verified = CanonicalCertificateSupport::verifyIntegrity($certificate, 'COVERAGE_CERT');
        $scoreResult = $verified['result'];

        if (! isset($scoreResult['globalScore'], $scoreResult['perModuleScores'], $scoreResult['pairScores'], $scoreResult['totalContracts'], $scoreResult['totalViolations'])
            || ! $this->verify($scoreResult)) {
            throw new InvalidArgumentException('COVERAGE_CERT_INVARIANT_FAILED: Sertifikat skor cakupan melanggar invarian permil.');
        }

        return true;
    }

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
