<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AviationFlightTransition extends Model
{
    public $timestamps = false;
    protected $table = 'avi_flight_transitions';

    protected $fillable = [
        'flight_id',
        'from_status',
        'to_status',
        'trigger',
        'actor_id',
        'notes',
        'created_at',
    ];

    public function flight(): BelongsTo
    {
        return $this->belongsTo(AviationFlight::class, 'flight_id');
    }
}

