<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Math;

use Brick\Math\BigRational;
use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;

final class CumulativeRoundingCertificateVerifier
{
    /**
     * Verifies cumulative rounding invariants:
     * - Conservation: sum of schedule equals total exactly
     * - Quota/deviation: every step is within 1 of its exact rational portion
     * - For unweighted: each step in {floor(total/n), ceil(total/n)}
     *
     * Complexity: O(N) time, O(1) auxiliary space.
     *
     * @throws InvalidCertificateException
     */
    public static function verify(CumulativeRoundingCertificate $certificate): void
    {
        $sum = 0;
        foreach ($certificate->schedule as $step) {
            if ($step < 0) {
                throw new InvalidCertificateException("Negative step detected: {$step}");
            }
            $sum += $step;
        }

        if ($sum !== $certificate->total) {
            throw new InvalidCertificateException(
                "Conservation invariant violated: sum of schedule ({$sum}) != total ({$certificate->total})"
            );
        }

        $one = BigRational::one();
        $negOne = BigRational::of('-1');

        foreach ($certificate->schedule as $idx => $step) {
            if (! isset($certificate->exactPortions[$idx])) {
                throw new InvalidCertificateException("Missing exact portion for index {$idx}");
            }

            $exact = $certificate->exactPortions[$idx];
            $diff = BigRational::of((string) $step)->minus($exact);

            // |step - exact| <= 1
            if ($diff->isGreaterThan($one) || $diff->isLessThan($negOne)) {
                throw new InvalidCertificateException(
                    "Step {$idx} ({$step}) deviates by more than 1 from exact portion ({$exact})"
                );
            }

            // For unweighted schedules, step must be either floor(total/n) or ceil(total/n)
            if ($certificate->weights === null && $certificate->periods > 0) {
                $floor = intdiv($certificate->total, $certificate->periods);
                $ceil = ($certificate->total % $certificate->periods === 0) ? $floor : $floor + 1;

                if ($step !== $floor && $step !== $ceil) {
                    throw new InvalidCertificateException(
                        "Step {$idx} ({$step}) is neither floor ({$floor}) nor ceil ({$ceil})"
                    );
                }
            }
        }
    }
}
