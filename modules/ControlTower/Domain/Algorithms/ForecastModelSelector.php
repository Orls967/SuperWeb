<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Algorithms;

use InvalidArgumentException;

/**
 * I2 Exact-Arithmetic Forecast Model Selector.
 *
 * Uses fixed-scale rational integer arithmetic (scale = 10,000) with zero
 * floating-point operations for all candidate evaluations, MAE/MAPE scoring,
 * next-period forecasts, and deterministic SHA-256 certificate hashing.
 */
final class ForecastModelSelector
{
    public const SCALE = 10_000;

    public const MODEL_SES = 'SES';

    public const MODEL_HOLT = 'HOLT';

    public const MODEL_CROSTON = 'CROSTON';

    /**
     * @var list<string>
     */
    private const CANDIDATE_ORDER = [
        self::MODEL_SES,
        self::MODEL_HOLT,
        self::MODEL_CROSTON,
    ];

    /**
     * @param  list<int>  $history
     * @return array{
     *     item_code: string,
     *     selected_model: string,
     *     forecast_qty: int,
     *     forecast_scaled: int,
     *     mae_scaled: int,
     *     mape_bps: int,
     *     evaluation_points: int,
     *     candidates: array<string, array{
     *         model: string,
     *         forecast_qty: int,
     *         forecast_scaled: int,
     *         mae_scaled: int,
     *         mape_bps: int,
     *         evaluation_points: int
     *     }>,
     *     certificate_hash: string
     * }
     */
    public function select(string $itemCode, array $history): array
    {
        $normalizedItem = trim($itemCode);
        if ($normalizedItem === '') {
            throw new InvalidArgumentException('INVALID_ITEM_CODE: Kode SKU item wajib diisi.');
        }

        $count = count($history);
        if ($count < 3) {
            throw new InvalidArgumentException('INVALID_FORECAST_HISTORY: Minimal 3 periode historis diperlukan.');
        }

        $normalizedHistory = [];
        foreach ($history as $qty) {
            if (! is_int($qty) || $qty < 0) {
                throw new InvalidArgumentException('NEGATIVE_DEMAND_HISTORY: Permintaan historis harus berupa integer >= 0.');
            }
            $normalizedHistory[] = $qty;
        }

        $evalStart = max(1, $count - 6);
        $evalCount = $count - $evalStart;

        $candidates = [];
        $best = null;

        foreach (self::CANDIDATE_ORDER as $model) {
            $seriesResult = match ($model) {
                self::MODEL_SES => $this->runSes($normalizedHistory),
                self::MODEL_HOLT => $this->runHolt($normalizedHistory),
                self::MODEL_CROSTON => $this->runCroston($normalizedHistory),
            };

            $predictionsScaled = $seriesResult['predictions_scaled'];
            $nextForecastScaled = max(0, $seriesResult['next_scaled']);
            $nextForecastQty = $this->roundDiv($nextForecastScaled, self::SCALE);

            $sumAbsErrScaled = 0;
            $sumApeBps = 0;
            $mapeCount = 0;

            for ($t = $evalStart; $t < $count; $t++) {
                $actual = $normalizedHistory[$t];
                $actualScaled = $actual * self::SCALE;
                $predScaled = max(0, $predictionsScaled[$t]);
                $absErrScaled = abs($actualScaled - $predScaled);

                $sumAbsErrScaled += $absErrScaled;
                if ($actual > 0) {
                    $sumApeBps += $this->roundDiv($absErrScaled, $actual);
                    $mapeCount++;
                }
            }

            $maeScaled = $this->roundDiv($sumAbsErrScaled, $evalCount);
            $mapeBps = $mapeCount > 0 ? $this->roundDiv($sumApeBps, $mapeCount) : 0;

            $candidateSummary = [
                'model' => $model,
                'forecast_qty' => $nextForecastQty,
                'forecast_scaled' => $nextForecastScaled,
                'mae_scaled' => $maeScaled,
                'mape_bps' => $mapeBps,
                'evaluation_points' => $evalCount,
            ];

            $candidates[$model] = $candidateSummary;

            // Strict `<` enforces deterministic tie-breaker: SES -> HOLT -> CROSTON
            if ($best === null || $maeScaled < $best['mae_scaled']) {
                $best = $candidateSummary;
            }
        }

        /** @var array{model: string, forecast_qty: int, forecast_scaled: int, mae_scaled: int, mape_bps: int, evaluation_points: int} $best */
        $certificateHash = hash('sha256', implode('|', [
            $normalizedItem,
            $best['model'],
            implode(',', $normalizedHistory),
            (string) $best['forecast_scaled'],
            (string) $best['mae_scaled'],
            (string) $best['mape_bps'],
        ]));

        return [
            'item_code' => $normalizedItem,
            'selected_model' => $best['model'],
            'forecast_qty' => $best['forecast_qty'],
            'forecast_scaled' => $best['forecast_scaled'],
            'mae_scaled' => $best['mae_scaled'],
            'mape_bps' => $best['mape_bps'],
            'evaluation_points' => $best['evaluation_points'],
            'candidates' => $candidates,
            'certificate_hash' => $certificateHash,
        ];
    }

    /**
     * Single Exponential Smoothing: alpha = 3/10.
     *
     * @param  list<int>  $history
     * @return array{predictions_scaled: array<int, int>, next_scaled: int}
     */
    private function runSes(array $history): array
    {
        $n = count($history);
        $level = $history[0] * self::SCALE;
        $predictions = [0 => $level];

        for ($t = 1; $t < $n; $t++) {
            $predictions[$t] = $level;
            $yScaled = $history[$t] * self::SCALE;
            $level = $this->roundDiv(3 * $yScaled + 7 * $level, 10);
        }

        return [
            'predictions_scaled' => $predictions,
            'next_scaled' => $level,
        ];
    }

    /**
     * Holt Linear Trend: alpha = 3/10, beta = 1/10.
     *
     * @param  list<int>  $history
     * @return array{predictions_scaled: array<int, int>, next_scaled: int}
     */
    private function runHolt(array $history): array
    {
        $n = count($history);
        $predictions = [
            0 => $history[0] * self::SCALE,
            1 => $history[0] * self::SCALE,
        ];

        $level = $history[1] * self::SCALE;
        $trend = ($history[1] - $history[0]) * self::SCALE;

        for ($t = 2; $t < $n; $t++) {
            $predictions[$t] = max(0, $level + $trend);
            $prevLevel = $level;
            $yScaled = $history[$t] * self::SCALE;
            $level = $this->roundDiv(3 * $yScaled + 7 * ($prevLevel + $trend), 10);
            $trend = $this->roundDiv(($level - $prevLevel) + 9 * $trend, 10);
        }

        return [
            'predictions_scaled' => $predictions,
            'next_scaled' => max(0, $level + $trend),
        ];
    }

    /**
     * Croston Intermittent Demand: alpha = 2/10.
     *
     * @param  list<int>  $history
     * @return array{predictions_scaled: array<int, int>, next_scaled: int}
     */
    private function runCroston(array $history): array
    {
        $n = count($history);
        $predictions = [0 => $history[0] * self::SCALE];

        $initialized = $history[0] > 0;
        $z = $history[0] * self::SCALE;
        $p = self::SCALE;
        $periodsSinceDemand = $initialized ? 0 : 1;

        for ($t = 1; $t < $n; $t++) {
            $predictions[$t] = $initialized ? $this->roundDiv($z * self::SCALE, $p) : 0;

            $y = $history[$t];
            if (! $initialized) {
                $periodsSinceDemand++;
                if ($y > 0) {
                    $initialized = true;
                    $z = $y * self::SCALE;
                    $p = $periodsSinceDemand * self::SCALE;
                    $periodsSinceDemand = 0;
                }
            } else {
                $periodsSinceDemand++;
                if ($y > 0) {
                    $z = $this->roundDiv(2 * ($y * self::SCALE) + 8 * $z, 10);
                    $p = $this->roundDiv(2 * ($periodsSinceDemand * self::SCALE) + 8 * $p, 10);
                    $periodsSinceDemand = 0;
                }
            }
        }

        $nextScaled = $initialized ? $this->roundDiv($z * self::SCALE, $p) : 0;

        return [
            'predictions_scaled' => $predictions,
            'next_scaled' => $nextScaled,
        ];
    }

    private function roundDiv(int $numerator, int $denominator): int
    {
        if ($denominator === 0) {
            return 0;
        }

        if ($numerator >= 0) {
            return intdiv($numerator + intdiv($denominator, 2), $denominator);
        }

        return -intdiv(-$numerator + intdiv($denominator, 2), $denominator);
    }
}
