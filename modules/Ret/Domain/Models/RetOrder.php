<?php

declare(strict_types=1);

namespace Modules\Ret\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class RetOrder extends Model
{
    protected $table = 'ret_orders';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'order_number',
        'customer_id',
        'channel_id',
        'total_amount_minor',
        'coupon_code',
        'status',
    ];

    protected $casts = [
        'total_amount_minor' => 'integer',
    ];
}
