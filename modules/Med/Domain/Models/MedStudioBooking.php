<?php

declare(strict_types=1);

namespace Modules\Med\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MedStudioBooking extends Model
{
    protected $table = 'med_studio_bookings';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'booking_code',
        'studio_id',
        'project_id',
        'client_entity_id',
        'start_time',
        'end_time',
        'total_price_minor',
        'status',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'total_price_minor' => 'integer',
    ];
}
