<?php

namespace Modules\Proptech\Domain\Models\Flex;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Proptech\Domain\Models\BuildingZone;

class FlexSpace extends Model
{
    protected $table = 'prp_flex_spaces';

    protected $guarded = [];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(BuildingZone::class, 'zone_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(FlexBooking::class, 'flex_space_id');
    }
}
