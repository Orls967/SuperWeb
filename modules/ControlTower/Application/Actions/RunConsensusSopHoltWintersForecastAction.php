<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Actions;

use InvalidArgumentException;
use Modules\ControlTower\Domain\Models\DemandForecast;

class RunConsensusSopHoltWintersForecastAction
{
    /**
     * @param  string  $period  YYYY-MM
     * @param  array<int>  $historicalDemands
     * @param  float  $alpha  Level smoothing factor (0 < alpha < 1)
     * @param  float  $beta  Trend smoothing factor (0 < beta < 1)
     * @return array{
     *     forecast: DemandForecast,
     *     forecast_qty: int,
     *     level: float,
     *     trend: float,
     *     algorithm_model: string
     * }
     */
    public function execute(
        string $itemCode,
        string $period,
        array $historicalDemands,
        float $alpha = 0.3,
        float $beta = 0.1,
        ?string $overrideReason = null
    ): array {
        if (count($historicalDemands) < 3) {
            throw new InvalidArgumentException('At least 3 historical demand periods are required for Holt-Winters forecasting.');
        }

        // Initialize Level and Trend from first two data points
        $level = (float) $historicalDemands[0];
        $trend = (float) ($historicalDemands[1] - $historicalDemands[0]);

        $count = count($historicalDemands);
        for ($t = 1; $t < $count; $t++) {
            $actual = (float) $historicalDemands[$t];
            $prevLevel = $level;
            $level = ($alpha * $actual) + ((1.0 - $alpha) * ($prevLevel + $trend));
            $trend = ($beta * ($level - $prevLevel)) + ((1.0 - $beta) * $trend);
        }

        $forecastQty = max(0, (int) round($level + $trend));
        $forecastCode = 'FC-'.$itemCode.'-'.str_replace('-', '', $period);

        $forecast = DemandForecast::updateOrCreate(
            ['forecast_code' => $forecastCode],
            [
                'item_code' => $itemCode,
                'period' => $period,
                'algorithm_model' => 'HOLT_WINTERS',
                'forecast_qty' => $forecastQty,
                'is_overridden' => $overrideReason !== null,
                'override_reason' => $overrideReason,
            ]
        );

        return [
            'forecast' => $forecast,
            'forecast_qty' => $forecastQty,
            'level' => round($level, 2),
            'trend' => round($trend, 2),
            'algorithm_model' => 'HOLT_WINTERS',
        ];
    }
}
