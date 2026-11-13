<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Algorithms;

use InvalidArgumentException;
use Modules\ControlTower\Domain\Certificates\ForecastAccuracyCertificate;

final class DemandForecastEngine
{
    /**
     * @param  list<int>  $history
     * @return array{
     *     item_code: string,
     *     model: string,
     *     forecast_qty: int,
     *     mape_bps: int,
     *     mad_units: int,
     *     tracking_signal_bps: int,
     *     certificate: ForecastAccuracyCertificate
     * }
     */
    public function forecast(
        string $itemCode,
        array $history,
        string $model = 'HOLT_WINTERS',
        int $alphaBps = 3000,
        int $betaBps = 1000,
        int $gammaBps = 2000,
    ): array {
        if ($itemCode === '' || count($history) < 3) {
            throw new InvalidArgumentException('INVALID_FORECAST_HISTORY: Minimal 3 periode historis diperlukan.');
        }

        foreach ($history as $qty) {
            if ($qty < 0) {
                throw new InvalidArgumentException('NEGATIVE_DEMAND_HISTORY: Permintaan historis tidak boleh negatif.');
            }
        }

        $n = count($history);
        $fitted = [];
        $forecastQty = match (strtoupper($model)) {
            'WEIGHTED_MOVING_AVG', 'MOVING_AVG' => $this->weightedMovingAverage($history, $fitted),
            'EXP_SMOOTHING' => $this->exponentialSmoothing($history, $alphaBps, $fitted),
            'LINEAR_REGRESSION' => $this->linearRegression($history, $fitted),
            default => $this->holtWintersAdditive($history, $alphaBps, $betaBps, $gammaBps, $fitted),
        };

        $forecastQty = max(0, $forecastQty);

        $absErrSum = 0;
        $signedErrSum = 0;
        $apeBpsSum = 0;
        $evalCount = 0;

        for ($i = 1; $i < $n; $i++) {
            $actual = $history[$i];
            $pred = $fitted[$i] ?? $history[$i - 1];
            $err = $actual - $pred;
            $absErr = abs($err);
            $absErrSum += $absErr;
            $signedErrSum += $err;
            if ($actual > 0) {
                $apeBpsSum += intdiv($absErr * 10_000, $actual);
            }
            $evalCount++;
        }

        $madUnits = $evalCount > 0 ? intdiv($absErrSum, $evalCount) : 0;
        $mapeBps = $evalCount > 0 ? intdiv($apeBpsSum, $evalCount) : 0;
        $trackingSignalBps = $madUnits > 0 ? intdiv($signedErrSum * 10_000, $madUnits * max(1, $evalCount)) : 0;

        $digest = hash('sha256', implode('|', [
            $itemCode,
            strtoupper($model),
            implode(',', $history),
            (string) $forecastQty,
            (string) $mapeBps,
            (string) $madUnits,
            (string) $trackingSignalBps,
        ]));

        return [
            'item_code' => $itemCode,
            'model' => strtoupper($model),
            'forecast_qty' => $forecastQty,
            'mape_bps' => $mapeBps,
            'mad_units' => $madUnits,
            'tracking_signal_bps' => $trackingSignalBps,
            'certificate' => new ForecastAccuracyCertificate(
                itemCode: $itemCode,
                model: strtoupper($model),
                history: $history,
                forecastQty: $forecastQty,
                mapeBps: $mapeBps,
                madUnits: $madUnits,
                trackingSignalBps: $trackingSignalBps,
                digest: $digest,
            ),
        ];
    }

    /**
     * @param  list<int>  $history
     * @param  array<int, int>  $fitted
     */
    private function weightedMovingAverage(array $history, array &$fitted): int
    {
        $n = count($history);
        $fitted[0] = $history[0];
        for ($i = 1; $i < $n; $i++) {
            if ($i < 3) {
                $fitted[$i] = $history[$i - 1];
            } else {
                $fitted[$i] = intdiv($history[$i - 3] * 1 + $history[$i - 2] * 2 + $history[$i - 1] * 3, 6);
            }
        }

        return intdiv($history[$n - 3] * 1 + $history[$n - 2] * 2 + $history[$n - 1] * 3, 6);
    }

    /**
     * @param  list<int>  $history
     * @param  array<int, int>  $fitted
     */
    private function exponentialSmoothing(array $history, int $alphaBps, array &$fitted): int
    {
        $alpha = max(100, min(9500, $alphaBps));
        $level = $history[0];
        $fitted[0] = $level;
        $n = count($history);

        for ($i = 1; $i < $n; $i++) {
            $fitted[$i] = $level;
            $level = intdiv($alpha * $history[$i] + (10_000 - $alpha) * $level, 10_000);
        }

        return $level;
    }

    /**
     * @param  list<int>  $history
     * @param  array<int, int>  $fitted
     */
    private function holtWintersAdditive(array $history, int $alphaBps, int $betaBps, int $gammaBps, array &$fitted): int
    {
        $alpha = max(100, min(9500, $alphaBps));
        $beta = max(100, min(9500, $betaBps));
        $gamma = max(100, min(9500, $gammaBps));

        $n = count($history);
        $level = $history[0];
        $trend = $history[1] - $history[0];
        $season = 0;
        $fitted[0] = $level;

        for ($i = 1; $i < $n; $i++) {
            $fitted[$i] = max(0, $level + $trend + $season);
            $prevLevel = $level;
            $level = intdiv($alpha * ($history[$i] - $season) + (10_000 - $alpha) * ($prevLevel + $trend), 10_000);
            $trend = intdiv($beta * ($level - $prevLevel) + (10_000 - $beta) * $trend, 10_000);
            $season = intdiv($gamma * ($history[$i] - $level) + (10_000 - $gamma) * $season, 10_000);
        }

        return max(0, $level + $trend + $season);
    }

    /**
     * @param  list<int>  $history
     * @param  array<int, int>  $fitted
     */
    private function linearRegression(array $history, array &$fitted): int
    {
        $n = count($history);
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumX2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $x = $i + 1;
            $y = $history[$i];
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }

        $denom = $n * $sumX2 - $sumX * $sumX;
        $slopeNum = $n * $sumXY - $sumX * $sumY;
        $interceptNum = $sumY * $sumX2 - $sumX * $sumXY;

        if ($denom === 0) {
            for ($i = 0; $i < $n; $i++) {
                $fitted[$i] = intdiv($sumY, $n);
            }

            return intdiv($sumY, $n);
        }

        for ($i = 0; $i < $n; $i++) {
            $x = $i + 1;
            $fitted[$i] = max(0, intdiv($slopeNum * $x + $interceptNum, $denom));
        }

        $nextX = $n + 1;

        return max(0, intdiv($slopeNum * $nextX + $interceptNum, $denom));
    }
}
