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

    protected $fillable = [
        'uuid',
        'productable_type',
        'productable_id',
        'seller_id',
        'category_id',
        'sku',
        'name',
        'slug',
        'description',
        'price',
        'compare_at_price',
        'cached_stock',
        'is_listed',
        'is_car',
        'weight_gram',
        'images',
    ];
}
