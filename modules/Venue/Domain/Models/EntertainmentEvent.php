<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EntertainmentEvent extends Model
{
    protected $table = 'ven_entertainment_events';

    protected $fillable = [
        'event_code',
        'venue_id',
        'title',
        'doors_open_at',
        'doors_closed_at',
        'ticket_floor_price_idr',
        'ticket_ceiling_price_idr',
        'current_ticket_price_idr',
        'status',
        'settled_at',
    ];

    protected $casts = [
        'doors_open_at' => 'datetime',
        'doors_closed_at' => 'datetime',
        'settled_at' => 'datetime',
        'ticket_floor_price_idr' => 'integer',
        'ticket_ceiling_price_idr' => 'integer',
        'current_ticket_price_idr' => 'integer',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(EntertainmentVenue::class, 'venue_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(EntertainmentTicket::class, 'event_id');
    }

    public function tiers(): HasMany
    {
        return $this->hasMany(TicketTier::class, 'event_id');
    }

    public function tableBookings(): HasMany
    {
        return $this->hasMany(EntertainmentTableBooking::class, 'event_id');
    }

    public function permits(): HasMany
    {
        return $this->hasMany(VenuePermit::class, 'event_id');
    }
}
