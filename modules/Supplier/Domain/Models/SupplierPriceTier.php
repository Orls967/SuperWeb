<?php

declare(strict_types=1);

namespace Modules\Supplier\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPriceTier extends Model
{
    protected $table = 'sup_price_tiers';

    protected $fillable = [
        'item_id', 'contract_id', 'min_qty', 'max_qty', 'unit_price', 'currency', 'valid_from', 'valid_to', 'is_active',
    ];

    protected $casts = [
        'min_qty' => 'integer', 'max_qty' => 'integer', 'unit_price' => 'string',
        'valid_from' => 'date', 'valid_to' => 'date', 'is_active' => 'boolean',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(SupplierItem::class, 'item_id');
    }
}
