<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class PriceTickAggregate extends Model
{
    protected $table = 'pric_price_tick_aggregates';

    protected $fillable = [
        'sku',
        'bucket_minute',
        'open_price_idr',
        'high_price_idr',
        'low_price_idr',
        'close_price_idr',
        'tick_count',
        'avg_demand_index',
    ];

    protected $casts = [
        'bucket_minute' => 'datetime',
        'open_price_idr' => 'integer',
        'high_price_idr' => 'integer',
        'low_price_idr' => 'integer',
        'close_price_idr' => 'integer',
        'tick_count' => 'integer',
        'avg_demand_index' => 'decimal:2',
    ];
}
