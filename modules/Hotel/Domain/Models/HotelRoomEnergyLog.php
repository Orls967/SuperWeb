<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class HotelRoomEnergyLog extends Model
{
    protected $table = 'htl_room_energy_logs';

    protected $fillable = [
        'property_id',
        'room_id',
        'log_date',
        'event_time',
        'motion_detected',
        'setback_active',
        'ac_power_watt',
        'setback_minutes',
        'energy_saved_wh',
        'esg_reported',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'log_date' => 'date',
        'event_time' => 'datetime',
        'motion_detected' => 'boolean',
        'setback_active' => 'boolean',
        'ac_power_watt' => 'integer',
        'setback_minutes' => 'integer',
        'energy_saved_wh' => 'integer',
        'esg_reported' => 'boolean',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(HotelProperty::class, 'property_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(HotelRoom::class, 'room_id');
    }
}
