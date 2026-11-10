<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Treasury\Domain\Algorithms\SeasonalCashForecaster;
use Modules\Treasury\Domain\DTOs\ForecastResultDTO;
use Modules\Treasury\Domain\Models\CashForecast;
use Psr\Clock\ClockInterface;

class GenerateSeasonalCashForecastAction
{
    public function __construct(
        protected readonly ?ClockInterface $clock = null,
        protected readonly SeasonalCashForecaster $forecaster = new SeasonalCashForecaster,
    ) {}

    protected function now(): CarbonImmutable
    {
        if ($this->clock !== null) {
            return CarbonImmutable::instance($this->clock->now());
        }

        return CarbonImmutable::now();
    }

    /**
     * Menghasilkan forecast arus kas 13 minggu dengan dekomposisi musiman.
     */
    public function execute(
        string|int $scenario = 'base',
        ?array $historyWeeks = null,
        ?int $openingBalanceIdr = null,
        ?string $startDate = null,
        ?array $historicalInflows = null,
        int $horizonWeeks = 13
    ): ForecastResultDTO {
        // Normalisasi argumen jika dipanggil via (scenario, historyWeeks) atau (openingBalanceIdr, startDate, historicalInflows, scenario)
        if (is_int($scenario) && $historyWeeks === null && is_string($historyWeeks) === false) {
            $openingBalance = $scenario;
            $start = $historyWeeks ?? ($startDate ?: $this->now()->toDateString());
            $inflows = $historicalInflows ?? [];
            $scen = is_string($scenario) ? $scenario : 'base';
        } else {
            $scen = is_string($scenario) ? $scenario : 'base';
            $inflows = $historyWeeks ?? $historicalInflows ?? [];
            $openingBalance = $openingBalanceIdr ?? 500_000_000;
            $start = $startDate ?? $this->now()->toDateString();
        }

        if (empty($inflows)) {
            $inflows = [
                140_000_000, 160_000_000, 150_000_000, 170_000_000,
                145_000_000, 165_000_000, 155_000_000, 175_000_000,
            ];
        }

        return DB::transaction(function () use ($openingBalance, $start, $inflows, $scen, $horizonWeeks) {
            CashForecast::where('scenario', $scen)->delete();

            $result = $this->forecaster->forecast($inflows, seasonPeriod: 4, horizonWeeks: $horizonWeeks);

            $runningBalance = $openingBalance;
            $startDateObj = CarbonImmutable::parse($start);

            $avgWeeklyInflow = (int) intdiv(array_sum($inflows), count($inflows));
            $baseWeeklyOutflow = (int) intdiv($avgWeeklyInflow * 75, 100);

            $scenarioOutflow = match ($scen) {
                'conservative' => (int) intdiv($baseWeeklyOutflow * 115, 100),
                'aggressive' => (int) intdiv($baseWeeklyOutflow * 90, 100),
                default => $baseWeeklyOutflow,
            };

            foreach ($result->projectedWeeks as $index => $projectedInflow) {
                $weekNumber = $index + 1;
                $weekStartDate = $startDateObj->addWeeks($index)->toDateString();

                $netFlow = $projectedInflow - $scenarioOutflow;
                $runningBalance += $netFlow;

                CashForecast::create([
                    'scenario' => $scen,
                    'week_number' => $weekNumber,
                    'start_date' => $weekStartDate,
                    'projected_inflow_idr' => $projectedInflow,
                    'projected_outflow_idr' => $scenarioOutflow,
                    'net_cash_flow_idr' => $netFlow,
                    'closing_balance_idr' => $runningBalance,
                    'history_cycles' => (int) intdiv(count($inflows), 4),
                    'ols_intercept' => (int) intdiv($result->interceptNumerator, $result->interceptDenominator),
                    'ols_slope' => (int) intdiv($result->slopeNumerator, $result->slopeDenominator),
                    'seasonal_indices' => $result->seasonalIndicesPermille,
                    'forecast_values' => $result->projectedWeeks,
                    'certificate' => $result->certificate->toArray(),
                ]);
            }

            return $result;
        });
    }
}
