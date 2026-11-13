<?php

declare(strict_types=1);

namespace Modules\Wms\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Wms\Domain\Models\Warehouse;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reservasi interval setengah-terbuka [start_min, end_min) pada zona lalu lintas (wms_zone_reservations).
 */
class ZoneReservation extends Model
{
    protected $table = 'wms_zone_reservations';

    protected $fillable = [
        'warehouse_id',
        'traffic_zone_id',
        'mission_id',
        'start_min',
        'end_min',
        'reservation_kind',
        'status',
        'released_at',
        'release_reason',
    ];

    protected $casts = [
        'traffic_zone_id' => 'integer',
        'mission_id' => 'integer',
        'start_min' => 'integer',
        'end_min' => 'integer',
        'released_at' => 'datetime',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function trafficZone(): BelongsTo
    {
        return $this->belongsTo(TrafficZone::class, 'traffic_zone_id');
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(RobotMission::class, 'mission_id');
    }
}
