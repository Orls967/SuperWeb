<?php

declare(strict_types=1);

namespace Modules\Ret\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Shared\Domain\Traits\HasUuid;

class RetCouponRedemption extends Model
{
    use HasUuid;

    protected $table = 'ret_coupon_redemptions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'coupon_code',
        'customer_id',
        'order_id',
        'channel_id',
        'discount_minor',
        'redeemed_at',
        'idempotency_key',
    ];

    protected $casts = [
        'discount_minor' => 'integer',
        'redeemed_at' => 'datetime',
    ];
}
