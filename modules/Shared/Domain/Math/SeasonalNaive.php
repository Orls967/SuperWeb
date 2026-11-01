<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Math;

use InvalidArgumentException;

/**
 * Seasonal Naive baseline forecasting method.
 *
 * Forecasts future periods by replicating the observed values from the same
 * relative position in the most recent complete seasonal cycle.
 *
 * Complexity: O(H) where H is the forecast horizon.
 */
final class SeasonalNaive
{
    /**
     * @param  list<int>  $history  Historical time series
     * @param  int  $seasonLength  Length of seasonal cycle (e.g. 7 for weekly)
     * @param  int  $horizon  Number of future steps to forecast
     * @return list<int>
     *
     * @throws InvalidArgumentException
     */
    public static function forecast(array $history, int $seasonLength = 7, int $horizon = 7): array
    {
        $n = count($history);
        if ($seasonLength <= 0) {
            throw new InvalidArgumentException("Season length must be positive, got: {$seasonLength}");
        }
        if ($horizon <= 0) {
            throw new InvalidArgumentException("Horizon must be positive, got: {$horizon}");
        }
        if ($n < $seasonLength) {
            throw new InvalidArgumentException("History length ({$n}) must be at least one season ({$seasonLength})");
        }

        $forecast = [];
        for ($h = 1; $h <= $horizon; $h++) {
            $offset = ($h - 1) % $seasonLength;
            $sourceIndex = $n - $seasonLength + $offset;
            $forecast[] = $history[$sourceIndex];
        }

        return $forecast;
    }
}
