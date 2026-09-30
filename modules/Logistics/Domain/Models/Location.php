<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Logistics\Domain\Enums\LocationType;

class Location extends LogisticsEntity
{
    use HasFactory;

    protected $table = 'lgx_locations';

    protected $fillable = [
        'code',
        'name',
        'type',
        'unlocode',
        'iata',
        'city',
        'province',
        'country',
        'lat_e6',
        'lng_e6',
        'timezone',
        'min_connection_minutes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => LocationType::class,
            'lat_e6' => 'integer',
            'lng_e6' => 'integer',
            'min_connection_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function outgoingLanes(): HasMany
    {
        return $this->hasMany(Lane::class, 'origin_id');
    }

    public function incomingLanes(): HasMany
    {
        return $this->hasMany(Lane::class, 'destination_id');
    }

    public function latitude(): float
    {
        return $this->lat_e6 / 1_000_000;
    }

    public function longitude(): float
    {
        return $this->lng_e6 / 1_000_000;
    }
}
