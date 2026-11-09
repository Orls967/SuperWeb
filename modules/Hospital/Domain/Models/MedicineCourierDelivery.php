<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicineCourierDelivery extends Model
{
    protected $table = 'hsp_medicine_courier_deliveries';

    protected $fillable = [
        'delivery_code',
        'epharmacy_order_id',
        'courier_service',
        'courier_name',
        'courier_phone',
        'recipient_name',
        'recipient_phone',
        'destination_address',
        'destination_latitude',
        'destination_longitude',
        'requires_cold_chain',
        'min_allowed_temp_c',
        'max_allowed_temp_c',
        'last_recorded_temp_c',
        'temperature_breached',
        'delivery_status',
        'pod_recipient_signature_hash',
        'pod_recipient_nik',
        'dispatched_at',
        'delivered_at',
    ];

    protected $casts = [
        'requires_cold_chain' => 'boolean',
        'temperature_breached' => 'boolean',
        'min_allowed_temp_c' => 'float',
        'max_allowed_temp_c' => 'float',
        'last_recorded_temp_c' => 'float',
        'destination_latitude' => 'float',
        'destination_longitude' => 'float',
        'pod_recipient_nik' => 'encrypted',
        'dispatched_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(EPharmacyOrder::class, 'epharmacy_order_id');
    }
}
