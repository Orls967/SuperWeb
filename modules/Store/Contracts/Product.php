<?php

declare(strict_types=1);

namespace Modules\Store\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Cross-module contract for Product model.
 */
class Product extends Model
{
    protected $table = 'store_products';
}
