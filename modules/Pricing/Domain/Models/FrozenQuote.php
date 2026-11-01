<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class FrozenQuote extends Model
{
    protected $table = 'pric_frozen_quotes';

    protected $fillable = [
        'quote_code',
        'sku',
        'frozen_price_idr',
        'floor_price_idr',
        'ceiling_price_idr',
        'quote_hash',
        'order_ref',
        'channel',
        'locked_until',
        'converted_at',
        'status',
        'metadata',
    ];

    protected $casts = [
        'frozen_price_idr' => 'integer',
        'floor_price_idr' => 'integer',
        'ceiling_price_idr' => 'integer',
        'locked_until' => 'datetime',
        'converted_at' => 'datetime',
        'metadata' => 'array',
    ];
}
