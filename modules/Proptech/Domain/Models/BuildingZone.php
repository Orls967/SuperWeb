<?php

declare(strict_types=1);

namespace Modules\Proptech\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BuildingZone extends Model
{
    protected $table = 'prp_building_zones';

    protected $fillable = [
        'zone_code',
        'building_code',
        'name',
        'floor_level',
        'area_sqm',
        'current_temp_c',
        'current_occupancy',
        'current_kwh_rate',
    ];

    public function sensors(): HasMany
    {
        return $this->hasMany(BuildingSensor::class, 'zone_id');
    }

    public function hvacCommands(): HasMany
    {
        return $this->hasMany(HvacCommand::class, 'zone_id');
    }

    public function billings(): HasMany
    {
        return $this->hasMany(ZoneUtilityBilling::class, 'zone_id');
    }
}
