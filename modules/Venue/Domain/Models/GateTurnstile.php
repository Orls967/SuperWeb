<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GateTurnstile extends Model
{
    protected $table = 'ven_gate_turnstiles';

    protected $fillable = [
        'venue_id',
        'zone_id',
        'gate_code',
        'gate_name',
        'turnstile_type',
        'is_emergency_exit',
        'is_locked',
        'total_scans_in',
        'total_scans_out',
        'last_scan_at',
    ];

    protected $casts = [
        'venue_id' => 'integer',
        'zone_id' => 'integer',
        'is_emergency_exit' => 'boolean',
        'is_locked' => 'boolean',
        'total_scans_in' => 'integer',
        'total_scans_out' => 'integer',
        'last_scan_at' => 'datetime',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(EntertainmentVenue::class, 'venue_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(EntertainmentZone::class, 'zone_id');
    }

    public function getHardwareIdentifierAttribute(): string
    {
        return $this->gate_code ?? ('TURNSTILE-'.$this->id);
    }
}
