<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models\Autonomous;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DroneUnit extends Model
{
    protected $table = 'lgx_drone_units';

    protected $fillable = [
        'drone_code',
        'model_name',
        'battery_percent',
        'max_payload_kg',
        'max_range_km',
        'status',
    ];

    public function missions(): HasMany
    {
        return $this->hasMany(DroneMission::class, 'drone_unit_id');
    }
}
