<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plant extends Model
{
    use HasUuids;

    protected $table = 'mfg_plants';

    protected $fillable = [
        'code', 'name', 'legal_entity_id', 'type', 'timezone',
        'nominal_capacity_per_day', 'capacity_uom', 'is_active', 'address',
    ];

    protected $casts = [
        'nominal_capacity_per_day' => 'integer', 'is_active' => 'boolean', 'address' => 'array',
    ];

    public function areas(): HasMany
    {
        return $this->hasMany(PlantArea::class, 'plant_id');
    }

    public function workCenters(): HasMany
    {
        return $this->hasMany(WorkCenter::class, 'plant_id');
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class, 'plant_id');
    }

    public function calendars(): HasMany
    {
        return $this->hasMany(WorkingCalendar::class, 'plant_id');
    }
}
