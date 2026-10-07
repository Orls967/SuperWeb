<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class CarbonCreditOrder extends Model
{
    protected $table = 'egy_carbon_credit_orders';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'carbon_credits_tons' => 'float',
        'price_per_ton_minor' => 'integer',
        'total_value_minor' => 'integer',
    ];
}
