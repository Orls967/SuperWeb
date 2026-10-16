<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class PriceTick extends Model
{
    protected $table = 'prc_price_ticks';

    protected $fillable = [
        'sku',
        'tick_time',
        'price_idr',
        'demand_index',
        'driver_source',
        'idempotency_key',
    ];

    protected $casts = [
        'tick_time' => 'datetime',
        'demand_index' => 'decimal:2',
    ];
}
