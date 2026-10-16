<?php

declare(strict_types=1);

namespace Modules\Ret\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class RetCouponRedemption extends Model
{
    protected $table = 'ret_coupon_redemptions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'coupon_code',
        'customer_id',
        'order_id',
        'redeemed_at',
    ];

    protected $casts = [
        'redeemed_at' => 'datetime',
    ];
}
