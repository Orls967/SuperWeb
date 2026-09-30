<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Logistics\Domain\Enums\TransportMode;

class ShipmentLeg extends LogisticsEntity
{
    protected $table = 'lgx_shipment_legs';

    protected $fillable = [
        'shipment_id',
        'schedule_id',
        'leg_sequence',
        'mode',
        'origin_location_id',
        'destination_location_id',
        'estimated_departure',
        'estimated_arrival',
        'actual_departure',
        'actual_arrival',
        'status',
    ];

    protected $casts = [
        'leg_sequence' => 'integer',
        'mode' => TransportMode::class,
        'estimated_departure' => 'datetime',
        'estimated_arrival' => 'datetime',
        'actual_departure' => 'datetime',
        'actual_arrival' => 'datetime',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    public function origin(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'origin_location_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }
}
