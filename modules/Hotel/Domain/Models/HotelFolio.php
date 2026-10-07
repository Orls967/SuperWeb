<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class HotelFolio extends Model
{
    protected $table = 'htl_folios';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'reservation_id',
        'guest_user_id',
        'total_room_charges',
        'total_addon_charges',
        'total_paid',
        'outstanding_balance',
        'status',
    ];

    protected $casts = [
        'total_room_charges' => 'integer',
        'total_addon_charges' => 'integer',
        'total_paid' => 'integer',
        'outstanding_balance' => 'integer',
    ];
}
