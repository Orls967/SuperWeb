<?php

namespace Modules\Proptech\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BuildingZone extends Model
{
    protected $table = 'prp_building_zones';

    protected $guarded = [];

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
