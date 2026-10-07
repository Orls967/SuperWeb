<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class HotelRoom extends Model
{
    protected $table = 'htl_rooms';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'property_id',
        'room_number',
        'room_type',
        'base_rate_idr',
        'status',
        'smart_lock_token',
        'smart_lock_expires_at',
        'energy_setback_active',
    ];

    protected $casts = [
        'base_rate_idr' => 'integer',
        'smart_lock_expires_at' => 'datetime',
        'energy_setback_active' => 'boolean',
    ];
}
