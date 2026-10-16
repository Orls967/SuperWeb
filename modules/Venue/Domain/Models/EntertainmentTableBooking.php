<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntertainmentTableBooking extends Model
{
    protected $table = 'ven_entertainment_table_bookings';

    protected $fillable = [
        'booking_code',
        'event_id',
        'table_number',
        'user_id',
        'minimum_spend_idr',
        'deposit_amount_idr',
        'status',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(EntertainmentEvent::class, 'event_id');
    }
}
