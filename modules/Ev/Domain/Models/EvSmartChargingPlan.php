<?php

declare(strict_types=1);

namespace Modules\Ev\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EvSmartChargingPlan extends Model
{
    protected $table = 'oto_ev_smart_charging_plans';

    protected $fillable = [
        'plan_uuid',
        'station_id',
        'time_slot',
        'allocated_power_kw',
        'certificate_json',
        'status',
    ];

    protected $casts = [
        'allocated_power_kw' => 'integer',
        'certificate_json' => 'array',
    ];

    public function station()
    {
        return $this->belongsTo(EvStation::class, 'station_id');
    }
}
