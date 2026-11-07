<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MiningGeotechIncident extends Model
{
    use HasUuids;

    protected $table = 'min_geotech_incidents';

    protected $fillable = [
        'id',
        'pit_id',
        'incident_code',
        'location_sector',
        'severity_level',
        'sensor_code',
        'sensor_type',
        'status',
        'alert_level',
        'displacement_mm',
        'displacement_rate_mm_hour',
        'cumulative_displacement_mm',
        'velocity_mm_day',
        'factor_of_safety',
        'factor_of_safety_fos',
        'pore_water_pressure_kpa',
        'crack_aperture_mm',
        'latitude',
        'longitude',
        'is_evacuation_ordered',
        'evacuated_at',
        'inspected_at',
        'remediated_at',
        'resolved_at',
        'geotech_engineer',
        'action_taken',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'displacement_mm' => 'float',
        'displacement_rate_mm_hour' => 'float',
        'cumulative_displacement_mm' => 'float',
        'velocity_mm_day' => 'float',
        'factor_of_safety' => 'float',
        'factor_of_safety_fos' => 'float',
        'pore_water_pressure_kpa' => 'float',
        'crack_aperture_mm' => 'float',
        'latitude' => 'float',
        'longitude' => 'float',
        'is_evacuation_ordered' => 'boolean',
        'evacuated_at' => 'datetime',
        'inspected_at' => 'datetime',
        'remediated_at' => 'datetime',
        'resolved_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function pit(): BelongsTo
    {
        return $this->belongsTo(MiningPit::class, 'pit_id');
    }
}
