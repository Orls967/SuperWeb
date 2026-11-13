<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Services;

use Modules\ControlTower\Application\Actions\OverrideDemandForecastAction;
use Modules\ControlTower\Domain\Algorithms\DemandForecastEngine;
use Modules\ControlTower\Domain\Models\DemandForecast;
use Modules\ControlTower\Domain\Models\ForecastOverride;
use Modules\ControlTower\Domain\Services\ForecastModelSelector;

final class DemandConsensusForecastService
{
    public function __construct(
        private readonly DemandForecastEngine $engine,
        private readonly ForecastModelSelector $selector,
        private readonly OverrideDemandForecastAction $overrideAction,
    ) {}

    /**
     * @param  list<int>  $history
     */
    public function generateBestConsensusForecast(string $itemCode, string $period, array $history): DemandForecast
    {
        $best = $this->selector->selectBestModel($itemCode, $history);
        $code = 'FC-'.strtoupper(substr(hash('sha256', $itemCode.'|'.$period.'|'.$best['best_model']), 0, 8));

        return DemandForecast::query()->updateOrCreate(
            ['item_code' => $itemCode, 'period' => $period, 'algorithm_model' => $best['best_model']],
            [
                'forecast_code' => $code,
                'forecast_qty' => $best['forecast_qty'],
                'actual_qty' => null,
                'mape_percent' => intdiv($best['mape_bps'], 100),
                'is_overridden' => false,
            ],
        );
    }

    public function applySalesOverride(DemandForecast $forecast, int $newQty, string $reason, ?int $userId = null): ForecastOverride
    {
        return $this->overrideAction->execute($forecast, $newQty, $reason, $userId);
    }
}
