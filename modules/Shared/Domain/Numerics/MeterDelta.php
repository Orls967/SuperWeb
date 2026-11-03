<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Numerics;

use InvalidArgumentException;

/**
 * Calculates consumption delta between cumulative meter readings,
 * handling register rollover with a known modulus.
 *
 * Complexity: O(1).
 */
final class MeterDelta
{
    /**
     * @param  int  $previousReading  Previous register reading (>= 0)
     * @param  int  $currentReading  Current register reading (>= 0)
     * @param  int|null  $modulus  Register rollover modulus (e.g. 100000 for 5 digits), or null if no rollover allowed
     * @return int Net consumption delta (>= 0)
     *
     * @throws InvalidArgumentException
     */
    public static function calculate(int $previousReading, int $currentReading, ?int $modulus = null): int
    {
        if ($previousReading < 0) {
            throw new InvalidArgumentException("Previous reading cannot be negative: {$previousReading}");
        }

        if ($currentReading < 0) {
            throw new InvalidArgumentException("Current reading cannot be negative: {$currentReading}");
        }

        if ($modulus !== null && $modulus <= 0) {
            throw new InvalidArgumentException("Modulus must be positive: {$modulus}");
        }

        if ($modulus !== null) {
            if ($previousReading >= $modulus) {
                throw new InvalidArgumentException("Previous reading {$previousReading} exceeds or equals modulus {$modulus}.");
            }
            if ($currentReading >= $modulus) {
                throw new InvalidArgumentException("Current reading {$currentReading} exceeds or equals modulus {$modulus}.");
            }
        }

        if ($currentReading >= $previousReading) {
            $delta = $currentReading - $previousReading;
        } else {
            if ($modulus === null) {
                throw new InvalidArgumentException("Current reading ({$currentReading}) is less than previous reading ({$previousReading}) and no rollover modulus was specified.");
            }
            $delta = ($modulus - $previousReading) + $currentReading;
        }

        MeterDeltaVerifier::verify($previousReading, $currentReading, $modulus, $delta);

        return $delta;
    }
}
