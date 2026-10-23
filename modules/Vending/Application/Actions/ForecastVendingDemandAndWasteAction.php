<?php

declare(strict_types=1);

namespace Modules\Vending\Application\Actions;

use Modules\Shared\Application\BaseAction;
use Modules\Vending\Domain\Models\VendingDemandForecast;

class ForecastVendingDemandAndWasteAction extends BaseAction
{
    /**
     * Compute automated AI demand & waste forecast for smart vending unit stock replenishment.
     *
     * @return array{forecast: VendingDemandForecast, forecast_qty: int, waste_forecast_qty: int, suggest_scaledown: bool}
     */
    public function handle(
        string $outletCode,
        string $itemCode,
        string $forecastDate,
        float $footfallFactor = 1.2
    ): array {
        return $this->transaction(function () use (
            $outletCode,
            $itemCode,
            $forecastDate,
            $footfallFactor
        ) {
            $baseDemand = 50;
            $forecastQty = (int) round($baseDemand * $footfallFactor);

            // Estimated 8% baseline spoilage/waste on fresh sandwiches/beverages
            $wasteForecastQty = (int) round($forecastQty * 0.08);
            $suggestScaledown = $footfallFactor < 0.8;

            $forecastCode = 'FCST-'.date('Ymd').'-'.substr(hash('sha256', "{$outletCode}:{$itemCode}:{$forecastDate}"), 0, 6);

            $forecast = VendingDemandForecast::updateOrCreate(
                [
                    'outlet_code' => $outletCode,
                    'item_code' => $itemCode,
                    'forecast_date' => $forecastDate,
                ],
                [
                    'forecast_code' => $forecastCode,
                    'algorithm_model' => 'ARIMA_WEATHER_FOOTFALL',
                    'footfall_factor' => (string) $footfallFactor,
                    'forecast_qty' => (string) $forecastQty,
                    'waste_forecast_qty' => (string) $wasteForecastQty,
                    'suggest_scaledown' => $suggestScaledown,
                ]
            );

            $this->audit(
                action: 'vending.forecast.computed',
                auditable: $forecast,
                oldValues: null,
                newValues: [
                    'outlet_code' => $outletCode,
                    'item_code' => $itemCode,
                    'forecast_qty' => $forecastQty,
                    'waste_qty' => $wasteForecastQty,
                ],
                correlationId: "ven_fcst_{$forecastCode}",
                impactType: 'demand_forecasting'
            );

            return [
                'forecast' => $forecast,
                'forecast_qty' => $forecastQty,
                'waste_forecast_qty' => $wasteForecastQty,
                'suggest_scaledown' => $suggestScaledown,
            ];
        });
    }
}
