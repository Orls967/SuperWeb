<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class PriceTickRejection extends Model
{
    protected $table = 'pric_tick_rejections';

    protected $fillable = [
        'sku',
        'slot_time',
        'attempted_price_idr',
        'base_price_idr',
        'last_price_idr',
        'raw_price_idr',
        'bounded_price_idr',
        'floor_price_idr',
        'ceiling_price_idr',
        'het_price_idr',
        'rejection_reason',
        'idempotency_key',
    ];

    protected $casts = [
        'slot_time' => 'datetime',
        'attempted_price_idr' => 'integer',
        'base_price_idr' => 'integer',
        'last_price_idr' => 'integer',
        'raw_price_idr' => 'integer',
        'bounded_price_idr' => 'integer',
        'floor_price_idr' => 'integer',
        'ceiling_price_idr' => 'integer',
        'het_price_idr' => 'integer',
    ];
}
