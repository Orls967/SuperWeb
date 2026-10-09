<?php

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntertainmentZone extends Model
{
    protected $table = 'ven_entertainment_zones';

    protected $guarded = [];

    protected $casts = [
        'crowd_lockdown_active' => 'boolean',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(EntertainmentVenue::class, 'venue_id');
    }
}
