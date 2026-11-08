<?php

declare(strict_types=1);

namespace Modules\Port\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FleetNoonReport extends Model
{
    protected $table = 'prt_fleet_noon_reports';

    protected $fillable = [
        'voyage_id',
        'vessel_id',
        'report_date',
        'latitude',
        'longitude',
        'course_degrees',
        'speed_log_knots',
        'speed_gps_knots',
        'distance_last_24h_nm',
        'wind_force_beaufort',
        'wind_direction',
        'sea_state_douglas',
        'engine_rpm',
        'engine_slip_percentage',
        'vlsfo_consumed_24h_mt',
        'lsmgo_consumed_24h_mt',
        'vlsfo_rob_mt',
        'lsmgo_rob_mt',
        'cargo_temperature_celsius',
        'remarks',
    ];

    protected $casts = [
        'report_date' => 'date',
        'latitude' => 'float',
        'longitude' => 'float',
        'course_degrees' => 'float',
        'speed_log_knots' => 'float',
        'speed_gps_knots' => 'float',
        'distance_last_24h_nm' => 'float',
        'wind_force_beaufort' => 'float',
        'sea_state_douglas' => 'float',
        'engine_rpm' => 'float',
        'engine_slip_percentage' => 'float',
        'vlsfo_consumed_24h_mt' => 'float',
        'lsmgo_consumed_24h_mt' => 'float',
        'vlsfo_rob_mt' => 'float',
        'lsmgo_rob_mt' => 'float',
        'cargo_temperature_celsius' => 'float',
    ];

    public function voyage(): BelongsTo
    {
        return $this->belongsTo(FleetVoyage::class, 'voyage_id');
    }

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(FleetVessel::class, 'vessel_id');
    }
}
