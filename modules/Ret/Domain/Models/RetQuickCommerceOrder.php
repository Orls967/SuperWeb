<?php

declare(strict_types=1);

namespace Modules\Ret\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class RetQuickCommerceOrder extends Model
{
    protected $table = 'ret_quick_commerce_orders';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

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
