<?php

declare(strict_types=1);

namespace Modules\Circular\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CirOrderLine extends Model
{
    protected $table = 'cir_order_lines';

    protected $fillable = [
        'order_id',
        'line_type',
        'material_code',
        'grade_code',
        'quantity_grams',
        'unit_price_per_kg_idr',
        'subtotal_idr',
    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'quantity_grams' => 'integer',
            'unit_price_per_kg_idr' => 'integer',
            'subtotal_idr' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(CirOrder::class, 'order_id');
    }
}
