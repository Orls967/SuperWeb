<?php

namespace Modules\Logistics\Domain\Models\Autonomous;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DroneUnit extends Model
{
    protected $table = 'lgx_drone_units';

    protected $guarded = [];

    public function missions(): HasMany
    {
        return $this->hasMany(DroneMission::class, 'drone_unit_id');
    }
}
