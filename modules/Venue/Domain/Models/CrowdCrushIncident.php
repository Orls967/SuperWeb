<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrowdCrushIncident extends Model
{
    protected $table = 'ven_crowd_crush_incidents';

    protected $fillable = [
        'incident_code',
        'zone_id',
        'event_id',
        'risk_level',
        'headcount_at_incident',
        'evacuation_corridor',
        'lockdown_initiated_at',
        'ambulance_dispatched',
        'status',
        'resolved_at',
        'reported_by_user_id',
    ];

    protected $casts = [
        'headcount_at_incident' => 'integer',
        'ambulance_dispatched' => 'boolean',
        'lockdown_initiated_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(EntertainmentZone::class, 'zone_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(EntertainmentEvent::class, 'event_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function getSeverityLevelAttribute(): ?string
    {
        return $this->risk_level;
    }
}
