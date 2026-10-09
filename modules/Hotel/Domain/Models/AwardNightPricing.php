<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AwardNightPricing extends Model
{
    protected $table = 'htl_award_night_pricings';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'property_id',
        'room_type',
        'stay_date',
        'occupancy_rate_percent',
        'base_award_pts',
        'dynamic_award_pts',
        'minimum_floor_pts',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(HotelProperty::class, 'property_id');
    }
}
