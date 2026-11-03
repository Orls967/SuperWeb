<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class PriceTick extends Model
{
    protected $table = 'pric_price_ticks';

    protected $fillable = [
        'sku',
        'tick_time',
        'slot_time',
        'price_idr',
        'base_price_idr',
        'raw_price_idr',
        'factor_total_bps',
        'demand_index',
        'driver_source',
        'status',
        'idempotency_key',
    ];

    protected $casts = [
        'tick_time' => 'datetime',
        'slot_time' => 'datetime',
        'price_idr' => 'integer',
        'base_price_idr' => 'integer',
        'raw_price_idr' => 'integer',
        'factor_total_bps' => 'integer',
        'demand_index' => 'decimal:2',
    ];
}
