<?php

declare(strict_types=1);

namespace Modules\Ret\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Shared\Domain\Traits\HasUuid;

class RetSellerSettlement extends Model
{
    use HasUuid;

    protected $table = 'ret_seller_settlements';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'settlement_code',
        'order_id',
        'seller_id',
        'seller_party_id',
        'verified_gmv_minor',
        'gross_order_amount_minor',
        'commission_rate_pct',
        'commission_amount_minor',
        'commission_fee_minor',
        'net_seller_payable_minor',
        'net_payout_minor',
        'status',
        'idempotency_key',
    ];

    protected $casts = [
        'verified_gmv_minor' => 'integer',
        'gross_order_amount_minor' => 'integer',
        'commission_amount_minor' => 'integer',
        'commission_fee_minor' => 'integer',
        'net_seller_payable_minor' => 'integer',
        'net_payout_minor' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(RetOrder::class, 'order_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(RetSeller::class, 'seller_id');
    }
}
