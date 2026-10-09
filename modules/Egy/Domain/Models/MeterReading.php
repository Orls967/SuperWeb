<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MeterReading extends Model
{
    protected $table = 'egy_meter_readings';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'kwh_consumed' => 'float',
        'is_peak_hour' => 'boolean',
        'rate_per_kwh_minor' => 'integer',
        'total_charge_minor' => 'integer',
        'interval_start' => 'datetime',
        'interval_end' => 'datetime',
    ];
}
