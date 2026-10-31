<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Math;

use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Mean Absolute Percentage Error (MAPE) calculator in basis points (bps).
 *
 * Complexity: O(N) time, O(1) space.
 */
final class Mape
{
    /**
     * @param  list<int>  $actual  Actual values (must be > 0)
     * @param  list<int>  $forecast  Forecasted values (must be non-negative)
     * @return int Error in basis points (1% = 100 bps)
     *
     * @throws InvalidArgumentException
     */
    public static function calculate(array $actual, array $forecast): int
    {
        $n = count($actual);
        if ($n === 0) {
            throw new InvalidArgumentException('Actual array cannot be empty');
        }

        if ($n !== count($forecast)) {
            throw new InvalidArgumentException('Actual and forecast arrays must have identical length');
        }

        $sumErrorRatio = BigRational::zero();
        $tenThousand = BigRational::of('10000');

        for ($i = 0; $i < $n; $i++) {
            $act = $actual[$i];
            $fc = $forecast[$i];

            if ($act <= 0) {
                throw new InvalidArgumentException("Actual value at index {$i} must be positive, got: {$act}");
            }
            if ($fc < 0) {
                throw new InvalidArgumentException("Forecast value at index {$i} must be non-negative, got: {$fc}");
            }

            $diff = abs($act - $fc);
            $ratio = BigRational::of((string) $diff)->dividedBy(BigRational::of((string) $act));
            $sumErrorRatio = $sumErrorRatio->plus($ratio);
        }

        $meanError = $sumErrorRatio->dividedBy(BigRational::of((string) $n));
        $meanErrorBps = $meanError->multipliedBy($tenThousand);

        return $meanErrorBps->toScale(0, RoundingMode::HalfUp)->toBigInteger()->toInt();
    }
}
