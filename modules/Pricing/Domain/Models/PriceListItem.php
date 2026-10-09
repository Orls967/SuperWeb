<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Item price list: SKU + harga + tier qty. */
class PriceListItem extends Model
{
    protected $table = 'pric_price_list_items';

    protected $fillable = ['price_list_id', 'sku', 'price_idr', 'min_qty'];

    protected $casts = ['price_idr' => 'integer', 'min_qty' => 'decimal:6'];

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class, 'price_list_id');
    }
}
