<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class RevenueCommandMetric extends Model
{
    protected $table = 'htl_revenue_command_metrics';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'metric_code',
        'region_city',
        'reporting_year',
        'reporting_month',
        'adr_idr',
        'occupancy_percentage',
        'revpar_idr',
        'total_venue_ticket_gmv_idr',
        'total_travel_bundle_gmv_idr',
    ];
}
