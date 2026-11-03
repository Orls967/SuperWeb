<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Numerics;

use UnexpectedValueException;

/**
 * Verifier for meter reading consumption delta calculations.
 */
final class MeterDeltaVerifier
{
    /**
     * @throws UnexpectedValueException
     */
    public static function verify(int $previousReading, int $currentReading, ?int $modulus, int $delta): bool
    {
        if ($delta < 0) {
            throw new UnexpectedValueException("Calculated delta cannot be negative: {$delta}");
        }

        if ($currentReading >= $previousReading) {
            $expected = $currentReading - $previousReading;
        } else {
            if ($modulus === null) {
                throw new UnexpectedValueException('Current reading is less than previous reading without modulus.');
            }
            $expected = ($modulus - $previousReading) + $currentReading;
        }

        if ($delta !== $expected) {
            throw new UnexpectedValueException("Delta mismatch: got {$delta}, expected {$expected}");
        }

        return true;
    }
}
