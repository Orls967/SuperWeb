<?php

declare(strict_types=1);

namespace Modules\Ret\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Shared\Domain\Traits\HasUuid;

class RetQuickCommerceOrder extends Model
{
    use HasUuid;

    protected $table = 'ret_quick_commerce_orders';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'order_id',
        'order_code',
        'dark_store_id',
        'customer_id',
        'placed_at',
        'promised_delivery_at',
        'actual_delivered_at',
        'picking_duration_seconds',
        'is_sla_breached',
        'compensation_credit_minor',
        'auto_credit_compensation_minor',
        'compensation_issued',
        'driver_type',
        'driver_user_id',
        'pod_signature_hash',
        'status',
        'idempotency_key',
    ];

    protected $casts = [
        'placed_at' => 'datetime',
        'promised_delivery_at' => 'datetime',
        'actual_delivered_at' => 'datetime',
        'picking_duration_seconds' => 'integer',
        'is_sla_breached' => 'boolean',
        'compensation_credit_minor' => 'integer',
        'auto_credit_compensation_minor' => 'integer',
        'compensation_issued' => 'boolean',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(RetOrder::class, 'order_id');
    }
}
