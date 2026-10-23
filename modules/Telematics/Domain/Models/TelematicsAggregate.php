<?php

declare(strict_types=1);

namespace Modules\Telematics\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class TelematicsAggregate extends Model
{
    protected $table = 'oto_telematics_aggregates';

    protected $fillable = [
        'device_id',
        'vehicle_id',
        'window_start',
        'window_end',
        'avg_speed_kmh',
        'max_rpm',
        'avg_oil_temp_c',
        'min_battery_voltage',
        'tick_count',
    ];

    protected $casts = [
        'window_start' => 'datetime',
        'window_end' => 'datetime',
        'avg_speed_kmh' => 'float',
        'max_rpm' => 'integer',
        'avg_oil_temp_c' => 'float',
        'min_battery_voltage' => 'float',
        'tick_count' => 'integer',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }
}
