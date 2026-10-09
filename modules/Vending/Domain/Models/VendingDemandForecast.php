<?php

namespace Modules\Vending\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class VendingDemandForecast extends Model
{
    protected $table = 'ven_demand_forecasts';

    protected $guarded = [];

    protected $casts = [
        'forecast_date' => 'date',
        'suggest_scaledown' => 'boolean',
    ];
}
