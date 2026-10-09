<?php

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EntertainmentVenue extends Model
{
    protected $table = 'ven_entertainment_venues';

    protected $guarded = [];

    public function zones(): HasMany
    {
        return $this->hasMany(EntertainmentZone::class, 'venue_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(EntertainmentEvent::class, 'venue_id');
    }
}
