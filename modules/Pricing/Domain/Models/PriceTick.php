<?php

namespace Modules\Pricing\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class PriceTick extends Model
{
    protected $table = 'prc_price_ticks';

    protected $guarded = [];

    protected $casts = [
        'tick_time' => 'datetime',
        'demand_index' => 'decimal:2',
    ];
}
