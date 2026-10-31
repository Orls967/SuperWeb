<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Math;

use Brick\Math\RoundingMode;
use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;

final class DagExplosionCertificateVerifier
{
    /**
     * Verifies that the Kahn topological traversal and independent DFS memoization
     * produced identical exact rational results for all raw ingredients, and that
     * the ceiled totals equal the mathematical ceiling of the exact rational value.
     *
     * Complexity: O(M) where M is raw ingredient count.
     *
     * @throws InvalidCertificateException
     */
    public static function verify(DagExplosionCertificate $certificate): void
    {
        $kahnKeys = array_keys($certificate->kahnTotals);
        sort($kahnKeys);
        $dfsKeys = array_keys($certificate->dfsTotals);
        sort($dfsKeys);

        if ($kahnKeys !== $dfsKeys) {
            throw new InvalidCertificateException('Ingredient keys mismatch between Kahn and DFS traversal');
        }

        foreach ($kahnKeys as $key) {
            $kahnVal = $certificate->kahnTotals[$key];
            $dfsVal = $certificate->dfsTotals[$key];

            if (! $kahnVal->isEqualTo($dfsVal)) {
                throw new InvalidCertificateException(
                    "Exact rational discrepancy for ingredient {$key}: Kahn ({$kahnVal}) != DFS ({$dfsVal})"
                );
            }

            if ($kahnVal->isLessThan(0)) {
                throw new InvalidCertificateException("Negative ingredient quantity detected for {$key}: {$kahnVal}");
            }

            if (! isset($certificate->ceiledTotals[$key])) {
                throw new InvalidCertificateException("Missing ceiled total for ingredient {$key}");
            }

            $expectedCeil = $kahnVal->getNumerator()->dividedBy($kahnVal->getDenominator(), RoundingMode::Ceiling)->toInt();
            $actualCeil = $certificate->ceiledTotals[$key];

            if ($actualCeil !== $expectedCeil) {
                throw new InvalidCertificateException(
                    "Ceil invariant violated for ingredient {$key}: got {$actualCeil}, expected {$expectedCeil}"
                );
            }
        }
    }
}
