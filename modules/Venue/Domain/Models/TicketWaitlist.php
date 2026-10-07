<?php

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketWaitlist extends Model
{
    protected $table = 'ven_ticket_waitlists';

    protected $fillable = [
        'waitlist_code',
        'event_id',
        'zone_id',
        'user_id',
        'offer_expires_at',
        'status',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(EntertainmentEvent::class, 'event_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(EntertainmentZone::class, 'zone_id');
    }
}
