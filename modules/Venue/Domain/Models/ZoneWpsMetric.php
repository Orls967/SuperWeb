<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZoneWpsMetric extends Model
{
    protected $table = 'ven_zone_wps_metrics';

    protected $fillable = [
        'event_id',
        'zone_id',
        'station_number',
        'active_servers_count',
        'arrival_rate_bps',
        'service_rate_bps',
        'utilization_bps',
        'average_wait_minutes',
        'wps_score',
        'reinforcement_needed',
        'recommended_additional_servers',
        'recorded_at',
    ];

    protected $casts = [
        'event_id' => 'integer',
        'zone_id' => 'integer',
        'station_number' => 'integer',
        'active_servers_count' => 'integer',
        'arrival_rate_bps' => 'integer',
        'service_rate_bps' => 'integer',
        'utilization_bps' => 'integer',
        'average_wait_minutes' => 'integer',
        'wps_score' => 'integer',
        'reinforcement_needed' => 'boolean',
        'recommended_additional_servers' => 'integer',
        'recorded_at' => 'datetime',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(EntertainmentZone::class, 'zone_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(EntertainmentEvent::class, 'event_id');
    }
}
