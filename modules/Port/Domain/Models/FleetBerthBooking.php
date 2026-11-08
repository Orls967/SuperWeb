<?php

declare(strict_types=1);

namespace Modules\Port\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FleetBerthBooking extends Model
{
    protected $table = 'prt_fleet_berth_bookings';

    protected $fillable = [
        'port_call_id',
        'berth_code',
        'window_start_at',
        'window_end_at',
        'allocated_length_m',
        'max_draft_m',
        'booking_status',
    ];

    protected $casts = [
        'window_start_at' => 'datetime',
        'window_end_at' => 'datetime',
        'allocated_length_m' => 'float',
        'max_draft_m' => 'float',
    ];

    public function portCall(): BelongsTo
    {
        return $this->belongsTo(FleetPortCall::class, 'port_call_id');
    }
}

