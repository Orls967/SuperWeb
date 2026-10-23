<?php

declare(strict_types=1);

namespace Modules\Ret\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class RetQuickCommerceOrder extends Model
{
    protected $table = 'ret_quick_commerce_orders';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'order_code',
        'dark_store_id',
        'customer_id',
        'placed_at',
        'promised_delivery_at',
        'actual_delivered_at',
        'picking_duration_seconds',
        'is_sla_breached',
        'auto_credit_compensation_minor',
        'compensation_issued',
        'status',
    ];

    protected $casts = [
        'placed_at' => 'datetime',
        'promised_delivery_at' => 'datetime',
        'actual_delivered_at' => 'datetime',
        'picking_duration_seconds' => 'integer',
        'is_sla_breached' => 'boolean',
        'auto_credit_compensation_minor' => 'integer',
        'compensation_issued' => 'boolean',
    ];
}
