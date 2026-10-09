<?php

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrowdSafetyTelemetry extends Model
{
    protected $table = 'ven_crowd_safety_telemetries';

    protected $fillable = [
        'telemetry_code',
        'zone_id',
        'current_headcount',
        'density_capacity_limit',
        'occupancy_percentage',
        'predicted_headcount_15m',
        'recommended_action',
        'gate_restricted',
    ];

    protected $casts = [
        'gate_restricted' => 'boolean',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(EntertainmentZone::class, 'zone_id');
    }
}
