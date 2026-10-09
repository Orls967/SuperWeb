<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Permintaan override harga di bawah margin minimum (44.5). */
class PriceOverride extends Model
{
    use HasUuids;

    protected $table = 'pric_price_overrides';

    protected $fillable = [
        'sku', 'channel', 'proposed_price_idr', 'floor_price_idr', 'margin_percent',
        'status', 'approval_id', 'reason', 'requested_by_user_id', 'decided_at',
    ];

    protected $casts = [
        'proposed_price_idr' => 'integer', 'floor_price_idr' => 'integer',
        'margin_percent' => 'decimal:4', 'approval_id' => 'integer',
        'requested_by_user_id' => 'integer', 'decided_at' => 'datetime',
    ];
}
