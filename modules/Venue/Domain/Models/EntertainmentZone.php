<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntertainmentZone extends Model
{
    protected $table = 'ven_entertainment_zones';

    protected $fillable = [
        'zone_code',
        'venue_id',
        'name',
        'capacity_limit',
        'current_occupancy',
        'crowd_lockdown_active',
    ];

    protected $casts = [
        'crowd_lockdown_active' => 'boolean',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(EntertainmentVenue::class, 'venue_id');
    }
}
