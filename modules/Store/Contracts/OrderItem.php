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
}
