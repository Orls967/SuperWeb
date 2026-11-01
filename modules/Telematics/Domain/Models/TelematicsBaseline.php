<?php

declare(strict_types=1);

namespace Modules\Telematics\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class TelematicsBaseline extends Model
{
    protected $table = 'oto_telematics_baselines';

    protected $fillable = [
        'vehicle_id',
        'metric',
        'median_value',
        'mad_value',
        'avg_oil_temp_c',
        'avg_battery_voltage',
        'avg_fuel_consumption_rate',
        'sample_count',
        'calculated_at',
        'window_days',
    ];

    protected $casts = [
        'calculated_at' => 'datetime',
        'median_value' => 'integer',
        'mad_value' => 'integer',
        'sample_count' => 'integer',
        'window_days' => 'integer',
    ];
}
