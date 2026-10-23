<?php

declare(strict_types=1);

namespace Modules\Store\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Cross-module contract for OrderItem model.
 */
class OrderItem extends Model
{
    protected $table = 'store_order_items';

    protected $fillable = [
        'order_id',
        'product_id',
        'name_snapshot',
        'price_snapshot',
        'qty',
        'line_total',
        'reservation_id',
    ];
}
