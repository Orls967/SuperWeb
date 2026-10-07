<?php

namespace Modules\Proptech\Domain\Models\Flex;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlexBooking extends Model
{
    protected $table = 'prp_flex_bookings';

    protected $guarded = [];

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
