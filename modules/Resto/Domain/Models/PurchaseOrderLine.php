<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderLine extends Model
{
    protected $table = 'resto_purchase_order_lines';

    protected $fillable = [
        'po_id',
        'ingredient_id',
        'qty_ordered',
        'unit',
        'qty_base_unit',
        'unit_price',
        'line_total',
        'qty_received',
    ];

    protected $casts = [
        'qty_ordered' => 'string',
        'qty_base_unit' => 'string',
        'qty_received' => 'string',
        'unit_price' => 'integer',
        'line_total' => 'integer',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id');
    }

    public function isFullyReceived(): bool
    {
        return (float) $this->qty_received >= (float) $this->qty_base_unit;
    }
}
