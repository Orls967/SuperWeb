<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Resto\Domain\Enums\DeliveryStatus;
use Modules\Shared\Domain\Traits\HasUuid;

class Delivery extends Model
{
    use HasUuid;

    protected $table = 'resto_deliveries';

    protected $fillable = [
        'uuid',
        'order_id',
        'outlet_id',
        'delivery_type',
        'courier_name',
        'courier_phone',
        'tracking_number',
        'recipient_name',
        'recipient_phone',
        'delivery_address',
        'distance_km',
        'delivery_fee',
        'packaging_fee',
        'status',
        'failure_reason',
        'shipped_at',
        'delivered_at',
    ];

    protected $casts = [
        'distance_km' => 'string',
        'delivery_fee' => 'integer',
        'packaging_fee' => 'integer',
        'status' => DeliveryStatus::class,
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }
}
