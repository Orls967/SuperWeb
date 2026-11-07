<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use App\Models\User;
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
        'actual_spend_idr',
        'shortfall_idr',
        'forfeited_deposit_idr',
        'idempotency_key',
        'status',
    ];

    protected $casts = [
        'minimum_spend_idr' => 'integer',
        'deposit_amount_idr' => 'integer',
        'actual_spend_idr' => 'integer',
        'shortfall_idr' => 'integer',
        'forfeited_deposit_idr' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(EntertainmentEvent::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
