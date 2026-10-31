<?php

declare(strict_types=1);

namespace Modules\Ev\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EvStation extends Model
{
    protected $table = 'oto_ev_stations';

    protected $attributes = [
        'address' => 'Jl. Jenderal Sudirman No. 1, Jakarta',
    ];

    protected $fillable = [
        'station_code',
        'name',
        'location_type',
        'address',
        'latitude',
        'longitude',
        'transformer_capacity_kw',
        'max_grid_power_kw',
        'timezone',
        'is_active',
    ];

    public function getMaxGridPowerKwAttribute(): int
    {
        return (int) ($this->attributes['max_grid_power_kw'] ?? $this->attributes['transformer_capacity_kw'] ?? 100);
    }

    protected $casts = [
        'transformer_capacity_kw' => 'integer',
        'is_active' => 'boolean',
    ];

    public function chargers()
    {
        return $this->hasMany(EvCharger::class, 'station_id');
    }
}
