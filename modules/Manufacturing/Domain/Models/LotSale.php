<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Penjualan per lot (jejak maju): lot → item order Store. */
class LotSale extends Model
{
    protected $table = 'mfg_lot_sales';

    protected $fillable = [
        'lot_id', 'store_order_item_id', 'store_order_id', 'user_id', 'qty', 'cost_idr',
    ];

    protected $casts = ['qty' => 'decimal:6', 'cost_idr' => 'integer', 'user_id' => 'integer'];

    public function lot(): BelongsTo
    {
        return $this->belongsTo(MaterialLot::class, 'lot_id');
    }
}
