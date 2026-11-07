<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TurnstileDevice extends Model
{
    protected $table = 'ven_turnstile_devices';

    protected $fillable = [
        'device_code',
        'zone_id',
        'hardware_serial',
        'firmware_version',
        'ip_address',
        'turnstile_status',
        'optical_beam_trips',
        'last_heartbeat_at',
    ];

    protected $casts = [
        'optical_beam_trips' => 'integer',
        'last_heartbeat_at' => 'datetime',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(EntertainmentZone::class, 'zone_id');
    }
}
