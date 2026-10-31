<?php

declare(strict_types=1);

namespace Modules\Vending\Application\Services;

use Modules\Shared\Domain\Math\HoltWintersAdditive;
use Modules\Shared\Domain\Math\Mape;
use Modules\Vending\Domain\Models\VendingDemandForecast;

class VendingForecastService
{
    /**
     * Generate 7-day demand and waste forecast using Holt-Winters additive exponential smoothing (B0′.5).
     *
     * @param  list<int>  $salesHistory
     */
    public function generate7DayForecast(
        string $outletCode,
        string $itemCode,
        array $salesHistory,
        int $seed = 42
    ): VendingDemandForecast {
        mt_srand($seed);

        $predictions = HoltWintersAdditive::forecast($salesHistory, horizon: 7);
        $forecastQty = max(10, (int) round(array_sum($predictions) / count($predictions)));
        $wasteForecastQty = (int) round($forecastQty * 0.08);

        $dateStr = now()->addDays(7)->toDateString();
        $forecastCode = 'FCST-'.strtoupper(substr(hash('sha256', "fc:{$outletCode}:{$itemCode}:{$dateStr}:{$seed}"), 0, 10));

        return VendingDemandForecast::updateOrCreate(
            ['forecast_code' => $forecastCode],
            [
                'outlet_code' => $outletCode,
                'item_code' => $itemCode,
                'forecast_date' => $dateStr,
                'algorithm_model' => 'HOLT_WINTERS_7D',
                'footfall_factor' => 100,
                'forecast_qty' => $forecastQty,
                'waste_forecast_qty' => $wasteForecastQty,
                'suggest_scaledown' => false,
            ]
        );
    }

    /**
     * Record actual demand and actual waste, compute MAPE error, and trigger scaledown flag if needed.
     */
    public function recordActualDemandAndWaste(int $forecastId, int $actualDemand, int $actualWaste): VendingDemandForecast
    {
        $forecast = VendingDemandForecast::findOrFail($forecastId);

        $mapeBps = Mape::calculate([max(1, $actualDemand)], [$forecast->forecast_qty]);
        $suggestScaledown = $actualWaste > ($forecast->waste_forecast_qty * 1.2);

        $forecast->update([
            'actual_qty' => $actualDemand,
            'waste_actual_qty' => $actualWaste,
            'mape_percent' => round($mapeBps / 100.0, 2),
            'suggest_scaledown' => $suggestScaledown,
        ]);

        return $forecast;
    }
}
