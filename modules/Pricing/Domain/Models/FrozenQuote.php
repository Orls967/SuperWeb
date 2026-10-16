<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class FrozenQuote extends Model
{
    protected $table = 'prc_frozen_quotes';

    protected $fillable = [
        'quote_code',
        'sku',
        'frozen_price_idr',
        'floor_price_idr',
        'ceiling_price_idr',
        'quote_hash',
        'locked_until',
        'status',
    ];

    protected $casts = [
        'locked_until' => 'datetime',
    ];
}
