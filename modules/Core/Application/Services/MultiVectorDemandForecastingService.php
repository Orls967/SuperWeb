<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

class MultiVectorDemandForecastingService
{
    /**
     * @param  list<int>  $actuals
     * @param  list<int>  $forecasts
     */
    public function calculateMape(array $actuals, array $forecasts): float
    {
        $count = count($actuals);
        if ($count === 0 || $count !== count($forecasts)) {
            return 0.0;
        }

        $sumPercentageError = 0.0;
        for ($i = 0; $i < $count; $i++) {
            $actual = $actuals[$i];
            $forecast = $forecasts[$i];
            if ($actual > 0) {
                $sumPercentageError += (abs($actual - $forecast) / $actual) * 100.0;
            }
        }

        return round($sumPercentageError / $count, 2);
    }
}
