<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class DistrictCoolingMeter extends Model
{
    protected $table = 'egy_district_cooling_meters';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'thermal_kwh_consumed' => 'float',
        'cop_efficiency_factor' => 'float',
        'rate_per_thermal_kwh_minor' => 'integer',
        'total_charge_minor' => 'integer',
    ];
}
