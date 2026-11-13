<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Actions;

use InvalidArgumentException;
use Modules\ControlTower\Domain\Algorithms\ForecastModelSelector;
use Modules\ControlTower\Domain\Models\DemandForecast;

final class RunForecastModelSelectionAction
{
    public function __construct(
        private readonly ForecastModelSelector $selector,
    ) {}

    /**
     * @param  list<int>  $history
     * @return array{
     *     forecast: DemandForecast,
     *     item_code: string,
     *     period: string,
     *     selected_model: string,
     *     forecast_qty: int,
     *     mae_scaled: int,
     *     mape_bps: int,
     *     candidates: array<string, mixed>,
     *     certificate_hash: string
     * }
     */
    public function execute(string $itemCode, string $period, array $history): array
    {
        $normalizedPeriod = trim($period);
        if (! preg_match('/^\d{4}-\d{2}$/', $normalizedPeriod)) {
            throw new InvalidArgumentException('INVALID_FORECAST_PERIOD: Format periode harus YYYY-MM.');
        }

        $selection = $this->selector->select($itemCode, $history);

        $forecastCode = 'FC-SEL-'.strtoupper(substr(hash('sha256', $selection['item_code'].'|'.$normalizedPeriod), 0, 12));

        $clampedBps = min(99_999, max(0, $selection['mape_bps']));
        $mapeFormatted = sprintf('%d.%02d', intdiv($clampedBps, 100), $clampedBps % 100);

        $forecast = DemandForecast::query()->updateOrCreate(
            ['forecast_code' => $forecastCode],
            [
                'item_code' => $selection['item_code'],
                'period' => $normalizedPeriod,
                'algorithm_model' => $selection['selected_model'],
                'forecast_qty' => $selection['forecast_qty'],
                'actual_qty' => null,
                'mape_percent' => $mapeFormatted,
                'mae_scaled' => $selection['mae_scaled'],
                'candidates_json' => $selection['candidates'],
                'certificate_hash' => $selection['certificate_hash'],
                'history_json' => array_values($history),
                'is_overridden' => false,
                'override_reason' => null,
            ],
        );

        return [
            'forecast' => $forecast,
            'item_code' => $selection['item_code'],
            'period' => $normalizedPeriod,
            'selected_model' => $selection['selected_model'],
            'forecast_qty' => $selection['forecast_qty'],
            'mae_scaled' => $selection['mae_scaled'],
            'mape_bps' => $selection['mape_bps'],
            'candidates' => $selection['candidates'],
            'certificate_hash' => $selection['certificate_hash'],
        ];
    }
}
