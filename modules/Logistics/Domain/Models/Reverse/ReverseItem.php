<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models\Reverse;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReverseItem extends Model
{
    protected $table = 'lgx_reverse_items';

    protected $fillable = [
        'reverse_order_id',
        'item_category',
        'description',
        'quantity_kg_or_units',
        'unit_of_measure',
        'recycling_target',
        'appraised_unit_price_idr',
        'subtotal_idr',
    ];

    protected $casts = [
        'quantity_kg_or_units' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ReverseOrder::class, 'reverse_order_id');
    }
}
