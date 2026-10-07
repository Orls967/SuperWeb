<?php

declare(strict_types=1);

namespace Modules\Ret\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class RetInventoryItem extends Model
{
    protected $table = 'ret_inventory_items';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'stock_available' => 'integer',
        'stock_reserved' => 'integer',
        'map_price_minor' => 'integer',
    ];
}
