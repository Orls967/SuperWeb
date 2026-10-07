<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TravelBundle extends Model
{
    protected $table = 'htl_travel_bundles';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'bundle_code',
        'user_id',
        'destination_city',
        'total_price_idr',
        'flight_vendor_share_idr',
        'hotel_vendor_share_idr',
        'transport_vendor_share_idr',
        'event_vendor_share_idr',
        'status',
    ];

    public function itineraries(): HasMany
    {
        return $this->hasMany(TravelItinerary::class, 'bundle_id');
    }
}
