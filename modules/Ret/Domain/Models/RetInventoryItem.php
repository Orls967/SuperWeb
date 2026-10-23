<?php

declare(strict_types=1);

namespace Modules\Ret\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class RetInventoryItem extends Model
{
    protected $table = 'ret_inventory_items';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'sku',
        'product_name',
        'stock_available',
        'stock_reserved',
        'map_price_minor',
    ];

    protected $casts = [
        'stock_available' => 'integer',
        'stock_reserved' => 'integer',
        'map_price_minor' => 'integer',
    ];
}
