<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class DemandForecast extends Model
{
    protected $table = 'sct_demand_forecasts';

    protected $guarded = [];

    protected $casts = [
        'forecast_qty' => 'integer',
        'actual_qty' => 'integer',
        'mape_percent' => 'float',
        'is_overridden' => 'boolean',
    ];
}
