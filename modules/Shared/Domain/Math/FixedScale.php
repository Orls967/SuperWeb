<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Math;

use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use DivisionByZeroError;
use InvalidArgumentException;

/**
 * Fixed Scale exact integer arithmetic with HalfUp rounding.
 *
 * Guarantees:
 * - Exact intermediate multiplication without 64-bit integer overflow via BigInteger
 * - Deterministic HalfUp rounding at line-item scale
 * - Property equivalence with BigRational HalfUp
 *
 * Complexity:
 * - Time: O(K) where K is factor count
 * - Space: O(1)
 */
final class FixedScale
{
    /**
     * Multiplies all factors and divides by divisor with HalfUp rounding.
     * The last argument is the divisor.
     *
     * @param  int  ...$args  At least one factor and one divisor (minimum 2 arguments)
     *
     * @throws InvalidArgumentException|DivisionByZeroError
     */
    public static function mulDivHalfUp(int ...$args): int
    {
        if (count($args) < 2) {
            throw new InvalidArgumentException('mulDivHalfUp requires at least one factor and one divisor');
        }

        $divisor = array_pop($args);
        if ($divisor === 0) {
            throw new DivisionByZeroError('Division by zero');
        }

        $product = BigInteger::one();
        foreach ($args as $factor) {
            $product = $product->multipliedBy($factor);
        }

        return $product->dividedBy($divisor, RoundingMode::HalfUp)->toInt();
    }
}
