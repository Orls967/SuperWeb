<?php

declare(strict_types=1);

namespace Modules\Port\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FleetVoyageLeg extends Model
{
    protected $table = 'prt_fleet_voyage_legs';

    protected $fillable = [
        'voyage_id',
        'leg_sequence',
        'leg_type',
        'departure_waypoint',
        'arrival_waypoint',
        'distance_nm',
        'target_speed_knots',
        'started_at',
        'completed_at',
        'bunker_consumed_mt',
        'status',
    ];

    protected $casts = [
        'distance_nm' => 'float',
        'target_speed_knots' => 'float',
        'bunker_consumed_mt' => 'float',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function voyage(): BelongsTo
    {
        return $this->belongsTo(FleetVoyage::class, 'voyage_id');
    }

    public function estimatedSteamingHours(): float
    {
        return $this->target_speed_knots > 0 ? ($this->distance_nm / $this->target_speed_knots) : 0.0;
    }
}

