<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    protected $casts = [
        'offer_expires_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(EntertainmentEvent::class, 'event_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(EntertainmentZone::class, 'zone_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(WaitlistReservation::class, 'waitlist_id');
    }
}
