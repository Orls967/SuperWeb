<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class CircularFlyAshSale extends Model
{
    protected $table = 'min_circular_fly_ash_sales';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'order_number',
        'cement_buyer_party_id',
        'fly_ash_tonnage',
        'price_per_ton_minor',
        'total_revenue_minor',
        'ledger_transaction_id',
    ];

    protected $casts = [
        'fly_ash_tonnage' => 'float',
        'price_per_ton_minor' => 'integer',
        'total_revenue_minor' => 'integer',
    ];
}
