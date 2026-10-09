<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class HotelReservation extends Model
{
    protected $table = 'htl_reservations';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'property_id',
        'room_id',
        'reservation_number',
        'guest_user_id',
        'check_in_date',
        'check_out_date',
        'daily_rate_idr',
        'total_room_charge_idr',
        'status',
    ];

    protected $casts = [
        'daily_rate_idr' => 'integer',
        'total_room_charge_idr' => 'integer',
        'check_in_date' => 'date',
        'check_out_date' => 'date',
    ];
}
