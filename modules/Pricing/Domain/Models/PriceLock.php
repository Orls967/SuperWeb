<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kunci harga immutable di dokumen order/contract (44.4). */
class PriceLock extends Model
{
    use HasUuids;

    protected $table = 'pric_price_locks';

    protected $fillable = [
        'subject_type', 'subject_id', 'sku', 'qty', 'list_price_idr',
        'applied_price_idr', 'discount_idr', 'price_list_id', 'source_rule_id',
        'source_kind', 'waterfall', 'reason', 'locked_at',
    ];

    protected $casts = [
        'qty' => 'decimal:6', 'list_price_idr' => 'integer', 'applied_price_idr' => 'integer',
        'discount_idr' => 'integer', 'waterfall' => 'array', 'locked_at' => 'datetime',
    ];

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class, 'price_list_id');
    }
}
