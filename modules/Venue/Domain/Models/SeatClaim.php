<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeatClaim extends Model
{
    protected $table = 'ven_seat_claims';

    protected $fillable = [
        'event_id',
        'tier_id',
        'section_code',
        'row_label',
        'seat_number',
        'status',
        'held_until',
        'hold_token',
        'buyer_user_id',
    ];

    protected $casts = [
        'seat_number' => 'integer',
        'held_until' => 'datetime',
        'buyer_user_id' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(EntertainmentEvent::class, 'event_id');
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(TicketTier::class, 'tier_id');
    }
}
