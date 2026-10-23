<?php

declare(strict_types=1);

namespace Modules\Proptech\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuildingSensor extends Model
{
    protected $table = 'prp_building_sensors';

    protected $fillable = [
        'sensor_code',
        'zone_id',
        'sensor_type',
        'reading_value',
        'idempotency_key',
        'is_online',
        'last_heartbeat_at',
        'recorded_at',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'last_heartbeat_at' => 'datetime',
        'reading_value' => 'decimal:2',
        'is_online' => 'boolean',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(BuildingZone::class, 'zone_id');
    }
}
