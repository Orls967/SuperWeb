<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Services;

use Modules\ControlTower\Domain\Algorithms\DemandForecastEngine;

final class ForecastModelSelector
{
    public function __construct(
        private readonly DemandForecastEngine $engine,
    ) {}

    /**
     * @param  list<int>  $history
     * @return array{best_model: string, forecast_qty: int, mape_bps: int}
     */
    public function selectBestModel(string $itemCode, array $history): array
    {
        $candidates = ['MOVING_AVG', 'EXP_SMOOTHING', 'HOLT_WINTERS', 'LINEAR_REGRESSION'];
        $best = null;

        foreach ($candidates as $model) {
            $res = $this->engine->forecast($itemCode, $history, $model);
            if ($best === null || $res['mape_bps'] < $best['mape_bps']) {
                $best = [
                    'best_model' => $res['model'],
                    'forecast_qty' => $res['forecast_qty'],
                    'mape_bps' => $res['mape_bps'],
                ];
            }
        }

        return $best ?? ['best_model' => 'HOLT_WINTERS', 'forecast_qty' => 0, 'mape_bps' => 0];
    }
}
