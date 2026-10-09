<?php

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntertainmentTicket extends Model
{
    protected $table = 'ven_entertainment_tickets';

    protected $guarded = [];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(EntertainmentEvent::class, 'event_id');
    }
}
