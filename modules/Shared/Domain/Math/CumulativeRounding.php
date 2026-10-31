<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Math;

use Brick\Math\BigRational;
use InvalidArgumentException;

/**
 * Cumulative Rounding for financial schedules (e.g. daily earned premium, amortization).
 *
 * Guarantees:
 * - Conservation invariant: sum of steps equals total exactly (last step absorbs residual)
 * - Bounded step variance: step_k in {floor(total/n), ceil(total/n)} for unweighted
 * - Certificate verification in production (PR2)
 *
 * Complexity:
 * - Time: O(N) where N is number of periods
 * - Space: O(N) to store schedule
 */
final class CumulativeRounding
{
    /**
     * @param  int  $total  Total amount to distribute across periods
     * @param  int  $periods  Number of periods (must be >= 1)
     * @return list<int>
     *
     * @throws InvalidArgumentException
     */
    public static function schedule(int $total, int $periods): array
    {
        if ($total < 0) {
            throw new InvalidArgumentException("Total must be non-negative, got: {$total}");
        }

        if ($periods <= 0) {
            throw new InvalidArgumentException("Periods must be strictly positive, got: {$periods}");
        }

        $schedule = [];
        $exactRatio = BigRational::of((string) $total)->dividedBy(BigRational::of((string) $periods));
        $exactPortions = array_fill(0, $periods, $exactRatio);

        $prevCumulative = 0;
        for ($k = 1; $k <= $periods; $k++) {
            $currCumulative = intdiv($total * $k, $periods);
            $step = $currCumulative - $prevCumulative;
            $schedule[] = $step;
            $prevCumulative = $currCumulative;
        }

        $cert = new CumulativeRoundingCertificate($total, $periods, $schedule, $exactPortions);
        CumulativeRoundingCertificateVerifier::verify($cert);

        return $schedule;
    }

    /**
     * Schedule with custom integer weights per period (e.g. days per month).
     *
     * @param  list<int>  $weights
     * @return list<int>
     *
     * @throws InvalidArgumentException
     */
    public static function scheduleWeighted(int $total, array $weights): array
    {
        if ($total < 0) {
            throw new InvalidArgumentException("Total must be non-negative, got: {$total}");
        }

        if (empty($weights)) {
            throw new InvalidArgumentException('Weights array cannot be empty');
        }

        $totalWeight = 0;
        foreach ($weights as $w) {
            if ($w <= 0) {
                throw new InvalidArgumentException("Each weight must be positive, got: {$w}");
            }
            $totalWeight += $w;
        }

        $schedule = [];
        $exactPortions = [];
        $totalRational = BigRational::of((string) $total);
        $totalWeightRational = BigRational::of((string) $totalWeight);

        $cumWeight = 0;
        $prevCumulative = 0;
        $periods = count($weights);

        foreach ($weights as $w) {
            $cumWeight += $w;
            $currCumulative = intdiv($total * $cumWeight, $totalWeight);
            $step = $currCumulative - $prevCumulative;
            $schedule[] = $step;
            $exactPortions[] = $totalRational->multipliedBy(BigRational::of((string) $w))->dividedBy($totalWeightRational);
            $prevCumulative = $currCumulative;
        }

        $cert = new CumulativeRoundingCertificate($total, $periods, $schedule, $exactPortions, $weights);
        CumulativeRoundingCertificateVerifier::verify($cert);

        return $schedule;
    }

    /**
     * Computes the cumulative earned/recognized amount up to elapsed periods.
     */
    public static function earnedTo(int $total, int $elapsed, int $periods): int
    {
        if ($total < 0) {
            throw new InvalidArgumentException("Total must be non-negative, got: {$total}");
        }

        if ($periods <= 0) {
            throw new InvalidArgumentException("Periods must be strictly positive, got: {$periods}");
        }

        if ($elapsed <= 0) {
            return 0;
        }

        if ($elapsed >= $periods) {
            return $total;
        }

        return intdiv($total * $elapsed, $periods);
    }
}
