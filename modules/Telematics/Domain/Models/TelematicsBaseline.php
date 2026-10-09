<?php

declare(strict_types=1);

namespace Modules\Telematics\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class TelematicsBaseline extends Model
{
    protected $table = 'oto_telematics_baselines';

    protected $fillable = [
        'vehicle_id',
        'avg_oil_temp_c',
        'avg_battery_voltage',
        'avg_fuel_consumption_rate',
        'sample_count',
        'calculated_at',
    ];

    protected $casts = [
        'calculated_at' => 'datetime',
        'avg_oil_temp_c' => 'float',
        'avg_battery_voltage' => 'float',
        'avg_fuel_consumption_rate' => 'float',
        'sample_count' => 'integer',
    ];
}
