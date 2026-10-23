<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class CarbonCreditOrder extends Model
{
    protected $table = 'egy_carbon_credit_orders';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'order_code',
        'seller_entity_id',
        'buyer_entity_id',
        'vintage_year',
        'carbon_credits_tons',
        'price_per_ton_minor',
        'total_value_minor',
        'status',
        'ledger_transaction_id',
    ];

    protected $casts = [
        'carbon_credits_tons' => 'float',
        'price_per_ton_minor' => 'integer',
        'total_value_minor' => 'integer',
    ];
}
