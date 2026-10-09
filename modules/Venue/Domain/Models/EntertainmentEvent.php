<?php

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EntertainmentEvent extends Model
{
    protected $table = 'ven_entertainment_events';

    protected $guarded = [];

    protected $casts = [
        'doors_open_at' => 'datetime',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(EntertainmentVenue::class, 'venue_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(EntertainmentTicket::class, 'event_id');
    }
}
