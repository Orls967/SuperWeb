<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class OfftakerContract extends Model
{
    protected $table = 'min_offtaker_contracts';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'contracted_tonnage' => 'float',
        'base_lme_price_usd_per_ton' => 'float',
        'premium_discount_usd' => 'float',
        'invoiced_amount_minor' => 'integer',
    ];
}
