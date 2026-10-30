<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Math;

use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;

final class RouteCertificateVerifier
{
    /**
     * Verifies that:
     * - The tour forms a valid Hamiltonian cycle starting and ending at node 0
     * - Every vertex 0..n-1 is visited exactly once
     * - Recomputed tour cost equals the reported cost
     * - If heuristic, reported cost >= lower bound, and gap bps is correctly calculated
     *
     * Complexity: O(N^2) time.
     *
     * @throws InvalidCertificateException
     */
    public static function verify(RouteCertificate $cert): void
    {
        $n = $cert->n;
        if ($n < 2) {
            if ($cert->tour !== [0, 0] || $cert->cost !== 0) {
                throw new InvalidCertificateException('Invalid trivial tour for n < 2');
            }

            return;
        }

        if (count($cert->tour) !== $n + 1) {
            throw new InvalidCertificateException("Tour length must be {$n} + 1, got: ".count($cert->tour));
        }

        if ($cert->tour[0] !== 0 || $cert->tour[$n] !== 0) {
            throw new InvalidCertificateException('Tour must start and end at node 0');
        }

        $visited = [];
        for ($i = 0; $i < $n; $i++) {
            $node = $cert->tour[$i];
            if ($node < 0 || $node >= $n) {
                throw new InvalidCertificateException("Tour contains invalid node index: {$node}");
            }
            if (isset($visited[$node])) {
                throw new InvalidCertificateException("Duplicate visit to node: {$node}");
            }
            $visited[$node] = true;
        }

        if (count($visited) !== $n) {
            throw new InvalidCertificateException('Tour does not visit all vertices exactly once');
        }

        // Recompute tour cost from matrix
        $recomputedCost = 0;
        for ($i = 0; $i < $n; $i++) {
            $u = $cert->tour[$i];
            $v = $cert->tour[$i + 1];
            $edgeCost = $cert->dist[$u][$v] ?? null;
            if ($edgeCost === null) {
                throw new InvalidCertificateException("Missing edge distance between {$u} and {$v}");
            }
            $recomputedCost += $edgeCost;
        }

        if ($recomputedCost !== $cert->cost) {
            throw new InvalidCertificateException(
                "Cost discrepancy: recomputed ({$recomputedCost}) != reported ({$cert->cost})"
            );
        }

        if ($cert->cost < $cert->lowerBound) {
            throw new InvalidCertificateException(
                "Reported cost ({$cert->cost}) violates lower bound ({$cert->lowerBound})"
            );
        }

        if (! $cert->isExact) {
            $expectedGap = ($cert->lowerBound === 0)
                ? 0
                : intdiv(($cert->cost - $cert->lowerBound) * 10_000, $cert->lowerBound);

            if ($cert->gapBps !== $expectedGap) {
                throw new InvalidCertificateException(
                    "Gap BPS discrepancy: reported ({$cert->gapBps}) != expected ({$expectedGap})"
                );
            }
        }
    }
}
