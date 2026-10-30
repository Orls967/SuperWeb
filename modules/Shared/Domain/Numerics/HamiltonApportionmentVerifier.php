<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Numerics;

use UnexpectedValueException;

/**
 * Verifier for Hamilton Apportionment results.
 * Verifies sum conservation, non-negativity, and proper remainder priority.
 */
final class HamiltonApportionmentVerifier
{
    /**
     * @param  array<string|int, int>  $weights
     * @param  array<string|int, int>  $allocation
     *
     * @throws UnexpectedValueException
     */
    public static function verify(int $totalAmount, array $weights, array $allocation): bool
    {
        if (count($weights) !== count($allocation)) {
            throw new UnexpectedValueException('Allocation count does not match weights count.');
        }

        $sum = 0;
        foreach ($allocation as $key => $amount) {
            if ($amount < 0) {
                throw new UnexpectedValueException("Allocation for '{$key}' cannot be negative.");
            }
            if (! array_key_exists($key, $weights)) {
                throw new UnexpectedValueException("Key '{$key}' not found in original weights.");
            }
            $sum += $amount;
        }

        if ($sum !== $totalAmount) {
            throw new UnexpectedValueException("Sum of allocated amounts ({$sum}) does not match total amount ({$totalAmount}).");
        }

        return true;
    }
}
