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
        'latitude_e6',
        'longitude_e6',
        'rpm',
        'speed_kmh',
        'oil_temp_c',
        'battery_voltage',
        'fuel_level_pct',
        'coolant_temp_deci_c',
        'battery_mv',
        'fuel_permil',
        'odometer_meters',
        'dtc_code',
        'dtc_codes_json',
        'idempotency_key',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'sequence' => 'integer',
        'rpm' => 'integer',
        'speed_kmh' => 'float',
        'coolant_temp_deci_c' => 'integer',
        'battery_mv' => 'integer',
        'fuel_permil' => 'integer',
        'odometer_meters' => 'integer',
        'latitude_e6' => 'integer',
        'longitude_e6' => 'integer',
        'dtc_codes_json' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \LogicException('TelematicsTick is append-only and cannot be updated.');
        });

        static::deleting(function () {
            throw new \LogicException('TelematicsTick is append-only and cannot be deleted.');
        });
    }
}
