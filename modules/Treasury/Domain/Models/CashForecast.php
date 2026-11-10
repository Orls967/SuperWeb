<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CashForecast extends Model
{
    use HasUuids;

    protected $table = 'trs_cash_forecasts';

    protected $fillable = [
        'scenario',
        'history_cycles',
        'ols_intercept',
        'ols_slope',
        'seasonal_indices',
        'forecast_values',
        'certificate',
        'week_number',
        'start_date',
        'projected_inflow_idr',
        'projected_outflow_idr',
        'net_cash_flow_idr',
        'closing_balance_idr',
    ];

    protected $casts = [
        'history_cycles' => 'integer',
        'ols_intercept' => 'integer',
        'ols_slope' => 'integer',
        'seasonal_indices' => 'array',
        'forecast_values' => 'array',
        'certificate' => 'array',
        'week_number' => 'integer',
        'start_date' => 'date',
        'projected_inflow_idr' => 'integer',
        'projected_outflow_idr' => 'integer',
        'net_cash_flow_idr' => 'integer',
        'closing_balance_idr' => 'integer',
    ];
}
