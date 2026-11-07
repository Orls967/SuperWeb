<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class TableRevpatMetric extends Model
{
    protected $table = 'ven_table_revpat_metrics';

    protected $fillable = [
        'table_code',
        'metric_date',
        'zone_code',
        'available_hours',
        'occupied_hours',
        'food_revenue_idr',
        'beverage_revenue_idr',
        'minimum_spend_shortfall_idr',
        'total_revenue_idr',
        'revpat_idr',
    ];

    protected $casts = [
        'metric_date' => 'date',
        'available_hours' => 'integer',
        'occupied_hours' => 'integer',
        'food_revenue_idr' => 'integer',
        'beverage_revenue_idr' => 'integer',
        'minimum_spend_shortfall_idr' => 'integer',
        'total_revenue_idr' => 'integer',
        'revpat_idr' => 'integer',
    ];
}
