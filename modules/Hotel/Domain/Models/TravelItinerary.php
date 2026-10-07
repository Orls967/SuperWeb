<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelItinerary extends Model
{
    protected $table = 'htl_itineraries';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'bundle_id',
        'day_number',
        'activity_name',
        'venue_type',
        'cancellation_penalty_fee_idr',
        'status',
    ];

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(TravelBundle::class, 'bundle_id');
    }
}
