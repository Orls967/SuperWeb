<?php

declare(strict_types=1);

namespace Modules\Telematics\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class TelematicsTick extends Model
{
    protected $table = 'oto_telematics_ticks';

    protected $fillable = [
        'device_id',
        'vehicle_id',
        'sequence',
        'recorded_at',
        'latitude',
        'longitude',
        'rpm',
        'speed_kmh',
        'oil_temp_c',
        'battery_voltage',
        'fuel_level_pct',
        'dtc_code',
        'idempotency_key',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'sequence' => 'integer',
        'rpm' => 'integer',
        'speed_kmh' => 'float',
        'oil_temp_c' => 'float',
        'battery_voltage' => 'float',
        'fuel_level_pct' => 'float',
    ];
}
