<?php

namespace Modules\Logistics\Domain\Models\Reverse;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReverseItem extends Model
{
    protected $table = 'lgx_reverse_items';

    protected $guarded = [];

    protected $casts = [
        'quantity_kg_or_units' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ReverseOrder::class, 'reverse_order_id');
    }
}
