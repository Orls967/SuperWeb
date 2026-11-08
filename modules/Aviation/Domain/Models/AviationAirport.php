<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AviationAirport extends Model
{
    protected $table = 'avi_airports';

    protected $fillable = [
        'iata_code',
        'icao_code',
        'name',
        'city',
        'province',
        'island',
        'latitude_x10000',
        'longitude_x10000',
        'elevation_m',
        'timezone',
        'slot_coordinated',
        'hourly_capacity',
        'status',
    ];

    protected $casts = [
        'slot_coordinated' => 'boolean',
        'hourly_capacity' => 'integer',
        'latitude_x10000' => 'integer',
        'longitude_x10000' => 'integer',
        'elevation_m' => 'integer',
    ];

    public function slots(): HasMany
    {
        return $this->hasMany(AviationSlot::class, 'airport_id');
    }

    public function outboundRoutes(): HasMany
    {
        return $this->hasMany(AviationRoute::class, 'origin_airport_id');
    }

    public function inboundRoutes(): HasMany
    {
        return $this->hasMany(AviationRoute::class, 'destination_airport_id');
    }
}

