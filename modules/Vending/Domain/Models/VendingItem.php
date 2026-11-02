<?php

declare(strict_types=1);

namespace Modules\Vending\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class VendingItem extends Model
{
    protected $table = 'vnd_items';

    protected $fillable = [
        'item_code',
        'name',
        'category',
        'base_price_idr',
        'cost_price_idr',
        'shelf_life_hours',
    ];

    protected $casts = [
        'base_price_idr' => 'integer',
        'cost_price_idr' => 'integer',
        'shelf_life_hours' => 'integer',
    ];
}
