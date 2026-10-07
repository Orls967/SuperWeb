<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MiningDispatchRun extends Model
{
    protected $table = 'min_dispatch_runs';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'site_id',
        'pit_id',
        'equipment_id',
        'shift_code',
        'target_payload_ton',
        'actual_payload_ton',
        'payload_variance_ton',
        'fuel_consumed_liter',
        'distance_km',
        'fuel_anomaly_detected',
        'contractor_payment_held',
        'status',
    ];

    protected $casts = [
        'target_payload_ton' => 'decimal:2',
        'actual_payload_ton' => 'decimal:2',
        'payload_variance_ton' => 'decimal:2',
        'fuel_consumed_liter' => 'decimal:2',
        'distance_km' => 'decimal:2',
        'fuel_anomaly_detected' => 'boolean',
        'contractor_payment_held' => 'boolean',
    ];
}
