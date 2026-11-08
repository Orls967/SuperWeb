<?php

declare(strict_types=1);

namespace Modules\Port\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FleetWeatherRouting extends Model
{
    protected $table = 'prt_fleet_weather_routings';

    protected $fillable = [
        'voyage_id',
        'advisory_source',
        'weather_pattern_alert',
        'significant_wave_height_m',
        'recommended_speed_reduction_knots',
        'diversion_distance_nm',
        'estimated_fuel_saving_mt',
        'route_diversion_accepted',
    ];

    protected $casts = [
        'significant_wave_height_m' => 'float',
        'recommended_speed_reduction_knots' => 'float',
        'diversion_distance_nm' => 'float',
        'estimated_fuel_saving_mt' => 'float',
        'route_diversion_accepted' => 'boolean',
    ];

    public function voyage(): BelongsTo
    {
        return $this->belongsTo(FleetVoyage::class, 'voyage_id');
    }
}
