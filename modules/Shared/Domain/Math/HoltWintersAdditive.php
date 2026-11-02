<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Math;

use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Additive Holt-Winters Exponential Smoothing (7-day seasonality) with SeasonalNaive fallback.
 *
 * Guarantees:
 * - Deterministic BigRational state transitions without floating-point drift
 * - Automatic fallback to SeasonalNaive when history < 2 seasons (14 days)
 * - Scale invariance: input * k -> output * k
 *
 * Complexity:
 * - Time: O(N + H) where N is history length and H is forecast horizon
 * - Space: O(M + H) where M is seasonal period (7)
 */
final class HoltWintersAdditive
{
    public const int DEFAULT_SEASON_LENGTH = 7;

    public const int DEFAULT_ALPHA_BPS = 2000; // 0.20

    public const int DEFAULT_BETA_BPS = 1000;  // 0.10

    public const int DEFAULT_GAMMA_BPS = 3000; // 0.30

    /**
     * @param  list<int>  $history  Historical daily demand/values (must be non-negative)
     * @param  int  $horizon  Forecast horizon (number of days ahead)
     * @param  int  $seasonLength  Seasonality length (default 7 days)
     * @param  int  $alphaBps  Level smoothing parameter in bps [0, 10000]
     * @param  int  $betaBps  Trend smoothing parameter in bps [0, 10000]
     * @param  int  $gammaBps  Seasonal smoothing parameter in bps [0, 10000]
     * @return list<int>
     *
     * @throws InvalidArgumentException
     */
    public static function forecast(
        array $history,
        int $horizon = 7,
        int $seasonLength = self::DEFAULT_SEASON_LENGTH,
        int $alphaBps = self::DEFAULT_ALPHA_BPS,
        int $betaBps = self::DEFAULT_BETA_BPS,
        int $gammaBps = self::DEFAULT_GAMMA_BPS,
    ): array {
        $n = count($history);
        if ($horizon <= 0) {
            throw new InvalidArgumentException("Horizon must be positive, got: {$horizon}");
        }
        if ($seasonLength <= 0) {
            throw new InvalidArgumentException("Season length must be positive, got: {$seasonLength}");
        }
        if ($alphaBps < 0 || $alphaBps > 10_000) {
            throw new InvalidArgumentException("Alpha BPS must be in [0, 10000], got: {$alphaBps}");
        }
        if ($betaBps < 0 || $betaBps > 10_000) {
            throw new InvalidArgumentException("Beta BPS must be in [0, 10000], got: {$betaBps}");
        }
        if ($gammaBps < 0 || $gammaBps > 10_000) {
            throw new InvalidArgumentException("Gamma BPS must be in [0, 10000], got: {$gammaBps}");
        }

        foreach ($history as $idx => $val) {
            if ($val < 0) {
                throw new InvalidArgumentException("History value at {$idx} cannot be negative: {$val}");
            }
        }

        // Fallback to SeasonalNaive if history < 2 seasons
        if ($n < 2 * $seasonLength) {
            return SeasonalNaive::forecast($history, $seasonLength, $horizon);
        }

        $m = BigRational::of($seasonLength);
        $tenThousand = BigRational::of(10_000);
        $alpha = BigRational::of($alphaBps)->dividedBy($tenThousand);
        $oneMinusAlpha = BigRational::one()->minus($alpha);
        $beta = BigRational::of($betaBps)->dividedBy($tenThousand);
        $oneMinusBeta = BigRational::one()->minus($beta);
        $gamma = BigRational::of($gammaBps)->dividedBy($tenThousand);
        $oneMinusGamma = BigRational::one()->minus($gamma);

        // Compute averages of first 2 seasons
        $sum1 = BigRational::zero();
        $sum2 = BigRational::zero();
        for ($i = 0; $i < $seasonLength; $i++) {
            $sum1 = $sum1->plus(BigRational::of($history[$i]));
            $sum2 = $sum2->plus(BigRational::of($history[$seasonLength + $i]));
        }
        $avg1 = $sum1->dividedBy($m);
        $avg2 = $sum2->dividedBy($m);

        // Initial Level and Trend
        $level = $avg1;
        $trend = $avg2->minus($avg1)->dividedBy($m);

        // Initial Seasonal indices
        $two = BigRational::of(2);
        $seasonals = [];
        for ($i = 0; $i < $seasonLength; $i++) {
            $dev1 = BigRational::of($history[$i])->minus($avg1);
            $dev2 = BigRational::of($history[$seasonLength + $i])->minus($avg2);
            $seasonals[$i] = $dev1->plus($dev2)->dividedBy($two);
        }

        // Run forward updates over entire history
        for ($t = 0; $t < $n; $t++) {
            $y = BigRational::of($history[$t]);
            $seasonIdx = $t % $seasonLength;
            $prevSeasonal = $seasonals[$seasonIdx];
            $prevLevel = $level;
            $prevTrend = $trend;

            // Level: alpha * (y - s) + (1 - alpha) * (L + b)
            $term1 = $alpha->multipliedBy($y->minus($prevSeasonal));
            $term2 = $oneMinusAlpha->multipliedBy($prevLevel->plus($prevTrend));
            $level = $term1->plus($term2);

            // Trend: beta * (L - prevL) + (1 - beta) * prevB
            $trendTerm1 = $beta->multipliedBy($level->minus($prevLevel));
            $trendTerm2 = $oneMinusBeta->multipliedBy($prevTrend);
            $trend = $trendTerm1->plus($trendTerm2);

            // Seasonal: gamma * (y - L) + (1 - gamma) * s
            $seasTerm1 = $gamma->multipliedBy($y->minus($level));
            $seasTerm2 = $oneMinusGamma->multipliedBy($prevSeasonal);
            $seasonals[$seasonIdx] = $seasTerm1->plus($seasTerm2);
        }

        // Forecast horizon
        $forecast = [];
        for ($h = 1; $h <= $horizon; $h++) {
            $seasonOffset = ($n + $h - 1) % $seasonLength;
            $hRational = BigRational::of($h);
            $fcRational = $level->plus($trend->multipliedBy($hRational))->plus($seasonals[$seasonOffset]);

            if ($fcRational->isNegative()) {
                $fcRational = BigRational::zero();
            }

            $forecast[] = $fcRational->toScale(0, RoundingMode::HalfUp)->toBigInteger()->toInt();
        }

        return $forecast;
    }
}
