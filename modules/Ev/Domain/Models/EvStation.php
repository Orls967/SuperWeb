<?php

declare(strict_types=1);

namespace Modules\Ev\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EvStation extends Model
{
    protected $table = 'oto_ev_stations';

    protected $fillable = [
        'station_code',
        'name',
        'location_type',
        'address',
        'latitude',
        'longitude',
        'transformer_capacity_kw',
        'is_active',
    ];

    protected $casts = [
        'transformer_capacity_kw' => 'integer',
        'is_active' => 'boolean',
    ];

    public function chargers()
    {
        return $this->hasMany(EvCharger::class, 'station_id');
    }
}
