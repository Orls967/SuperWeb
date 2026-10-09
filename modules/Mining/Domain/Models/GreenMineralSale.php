<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class GreenMineralSale extends Model
{
    protected $table = 'min_green_mineral_sales';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'tonnage' => 'float',
        'base_price_minor' => 'integer',
        'green_premium_adder_minor' => 'integer',
        'total_settled_minor' => 'integer',
    ];
}
