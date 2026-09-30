<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CapacityReservation extends LogisticsEntity
{
    protected $table = 'lgx_capacity_reservations';

    protected $fillable = [
        'schedule_id',
        'shipment_id',
        'idempotency_key',
        'allocated_weight_kg',
        'allocated_volume_dm3',
        'allocated_teu',
        'allocated_uld_positions',
        'status',
    ];

    protected $casts = [
        'allocated_weight_kg' => 'decimal:3',
        'allocated_volume_dm3' => 'integer',
        'allocated_teu' => 'integer',
        'allocated_uld_positions' => 'integer',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isReleased(): bool
    {
        return $this->status === 'released';
    }
}
