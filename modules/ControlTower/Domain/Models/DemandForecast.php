<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class DemandForecast extends Model
{
    protected $table = 'sct_demand_forecasts';

    protected $fillable = [
        'forecast_code',
        'item_code',
        'period',
        'algorithm_model',
        'forecast_qty',
        'actual_qty',
        'mape_percent',
        'is_overridden',
        'override_reason',
    ];

    protected $casts = [
        'forecast_qty' => 'integer',
        'actual_qty' => 'integer',
        'mape_percent' => 'float',
        'is_overridden' => 'boolean',
    ];
}
