<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EntertainmentVenue extends Model
{
    protected $table = 'ven_entertainment_venues';

    protected $fillable = [
        'venue_code',
        'name',
        'venue_type',
        'city',
        'max_legal_capacity',
        'min_age_requirement',
    ];

    public function zones(): HasMany
    {
        return $this->hasMany(EntertainmentZone::class, 'venue_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(EntertainmentEvent::class, 'venue_id');
    }
}
