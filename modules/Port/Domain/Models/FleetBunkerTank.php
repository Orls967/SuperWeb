<?php

declare(strict_types=1);

namespace Modules\Port\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FleetBunkerTank extends Model
{
    protected $table = 'prt_fleet_bunker_tanks';

    protected $fillable = [
        'vessel_id',
        'tank_code',
        'tank_name',
        'fuel_grade',
        'capacity_m3',
        'safe_capacity_m3',
        'current_rob_m3',
        'current_rob_metric_tons',
        'sounding_depth_cm',
    ];

    protected $casts = [
        'capacity_m3' => 'float',
        'safe_capacity_m3' => 'float',
        'current_rob_m3' => 'float',
        'current_rob_metric_tons' => 'float',
        'sounding_depth_cm' => 'float',
    ];

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(FleetVessel::class, 'vessel_id');
    }

    public function availableUllageM3(): float
    {
        return max(0.0, $this->safe_capacity_m3 - $this->current_rob_m3);
    }
}
