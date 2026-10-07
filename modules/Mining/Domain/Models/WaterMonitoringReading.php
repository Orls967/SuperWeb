<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class WaterMonitoringReading extends Model
{
    protected $table = 'min_water_monitoring_readings';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'water_volume_m3' => 'float',
        'effluent_ph' => 'float',
        'effluent_tss_mg_l' => 'float',
        'threshold_exceeded' => 'boolean',
        'environmental_penalty_minor' => 'integer',
        'water_fee_minor' => 'integer',
        'sampled_at' => 'datetime',
    ];
}
