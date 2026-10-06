<?php

namespace Modules\B2b\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class WholesaleCatalog extends Model
{
    use HasUuids;

    protected $table = 'b2b_wholesale_catalogs';

    protected $fillable = [
        'sku',
        'vendor_id',
        'product_name',
        'category',
        'base_price_idr',
        'min_order_qty',
        'tiered_pricing_matrix',
        'available_stock',
        'status',
    ];

    protected $casts = [
        'base_price_idr' => 'integer',
        'min_order_qty' => 'integer',
        'available_stock' => 'integer',
        'tiered_pricing_matrix' => 'array',
    ];
}
