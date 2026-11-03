<?php

declare(strict_types=1);

namespace Modules\Circular\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CirSettlement extends Model
{
    protected $table = 'cir_settlements';

    protected $fillable = [
        'order_id',
        'seller_party_id',
        'gross_amount_idr',
        'fee_bps',
        'fee_amount_idr',
        'net_seller_amount_idr',
        'is_paid_out',
        'idempotency_key',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'seller_party_id' => 'integer',
            'gross_amount_idr' => 'integer',
            'fee_bps' => 'integer',
            'fee_amount_idr' => 'integer',
            'net_seller_amount_idr' => 'integer',
            'is_paid_out' => 'boolean',
            'settled_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(CirOrder::class, 'order_id');
    }
}
