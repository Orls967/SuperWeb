<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaitlistReservation extends Model
{
    protected $table = 'ven_waitlist_reservations';

    protected $fillable = [
        'waitlist_id',
        'token',
        'offered_at',
        'expires_at',
        'claimed_at',
        'status',
    ];

    protected $casts = [
        'offered_at' => 'datetime',
        'expires_at' => 'datetime',
        'claimed_at' => 'datetime',
    ];

    public function waitlist(): BelongsTo
    {
        return $this->belongsTo(TicketWaitlist::class, 'waitlist_id');
    }
}
