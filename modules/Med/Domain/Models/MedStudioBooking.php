<?php

declare(strict_types=1);

namespace Modules\Med\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MedStudioBooking extends Model
{
    protected $table = 'med_studio_bookings';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'total_price_minor' => 'integer',
    ];
}
