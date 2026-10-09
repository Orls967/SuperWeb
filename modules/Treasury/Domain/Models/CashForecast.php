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
        'week_number',
        'start_date',
        'projected_inflow_idr',
        'projected_outflow_idr',
        'net_cash_flow_idr',
        'closing_balance_idr',
    ];

    protected $casts = [
        'week_number' => 'integer',
        'start_date' => 'date',
        'projected_inflow_idr' => 'integer',
        'projected_outflow_idr' => 'integer',
        'net_cash_flow_idr' => 'integer',
        'closing_balance_idr' => 'integer',
    ];
}
