<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class GreenLeaseDiscount extends Model
{
    protected $table = 'egy_green_lease_discounts';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'esg_score' => 'integer',
        'discount_rate_pct' => 'float',
        'gross_utility_charge_minor' => 'integer',
        'green_discount_amount_minor' => 'integer',
        'net_utility_charge_minor' => 'integer',
    ];
}
