<?php

declare(strict_types=1);

namespace Modules\Wms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Zona lalu lintas robot gudang dengan kapasitas k dan blokir staf (wms_traffic_zones, Fase 279).
 */
class TrafficZone extends Model
{
    protected $table = 'wms_traffic_zones';

    protected $fillable = [
        'warehouse_id',
        'zone_code',
        'zone_kind',
        'capacity_k',
        'staff_blocked',
        'block_start_min',
        'block_end_min',
        'block_reason',
    ];

    protected $casts = [
        'capacity_k' => 'integer',
        'staff_blocked' => 'boolean',
        'block_start_min' => 'integer',
        'block_end_min' => 'integer',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(ZoneReservation::class, 'traffic_zone_id');
    }
}
