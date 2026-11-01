<?php

declare(strict_types=1);

namespace Modules\Vending\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class VendingDemandForecast extends Model
{
    protected $table = 'vnd_demand_forecasts';

    protected $fillable = [
        'forecast_code',
        'outlet_code',
        'item_code',
        'forecast_date',
        'algorithm_model',
        'footfall_factor',
        'forecast_qty',
        'actual_qty',
        'mape_percent',
        'waste_forecast_qty',
        'waste_actual_qty',
        'waste_mape_percent',
        'suggest_scaledown',
    ];

    protected $casts = [
        'forecast_date' => 'date',
        'suggest_scaledown' => 'boolean',
    ];
}
