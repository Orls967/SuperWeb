<?php

declare(strict_types=1);

namespace Modules\Proptech\Domain\Models\Flex;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlexBooking extends Model
{
    protected $table = 'prp_flex_bookings';

    protected $fillable = [
        'booking_code',
        'flex_space_id',
        'user_id',
        'start_time',
        'end_time',
        'deposit_amount_idr',
        'total_price_idr',
        'status',
        'checked_in_passport_hash',
        'checked_in_at',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'checked_in_at' => 'datetime',
    ];

    public function space(): BelongsTo
    {
        return $this->belongsTo(FlexSpace::class, 'flex_space_id');
    }
}
