<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class WaterNetwork extends Model
{
    protected $table = 'egy_water_networks';

    protected $fillable = [
        'network_name',
        'zone_code',
        'capacity_liters_per_day',
        'water_source_type',
        'status',
    ];

    protected $casts = [
        'capacity_liters_per_day' => 'integer',
    ];

    public function nodes(): HasMany
    {
        return $this->hasMany(WaterNode::class, 'network_id');
    }

    public function meterReadings(): HasMany
    {
        return $this->hasMany(WaterMeterReading::class, 'network_id');
    }

    public function leakIncidents(): HasMany
    {
        return $this->hasMany(LeakIncident::class, 'network_id');
    }
}
