<?php

declare(strict_types=1);

namespace Modules\Distribution\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Baris order distributor: alokasi & backorder per baris. */
class DistOrderLine extends Model
{
    protected $table = 'dist_order_lines';

    protected $fillable = [
        'order_id', 'product_id', 'sku', 'name_snapshot', 'qty', 'allocated_qty',
        'shipped_qty', 'unit_price_idr', 'line_total_idr', 'line_status',
    ];

    protected $casts = [
        'product_id' => 'integer', 'qty' => 'decimal:6', 'allocated_qty' => 'decimal:6',
        'shipped_qty' => 'decimal:6', 'unit_price_idr' => 'decimal:2', 'line_total_idr' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(DistOrder::class, 'order_id');
    }

    public function backorderQty(): float
    {
        return max(0.0, (float) $this->qty - (float) $this->allocated_qty);
    }
}
