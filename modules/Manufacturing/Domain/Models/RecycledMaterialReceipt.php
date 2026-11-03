<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecycledMaterialReceipt extends Model
{
    protected $table = 'mfg_recycled_receipts';

    protected $fillable = [
        'reverse_shipment_id',
        'material_lot_id',
        'material_code',
        'grade_code',
        'quantity_grams',
        'unit_price_per_kg_idr',
        'total_value_idr',
        'idempotency_key',
    ];

    protected $casts = [
        'quantity_grams' => 'integer',
        'unit_price_per_kg_idr' => 'integer',
        'total_value_idr' => 'integer',
    ];

    public function materialLot(): BelongsTo
    {
        return $this->belongsTo(MaterialLot::class, 'material_lot_id', 'id');
    }
}
