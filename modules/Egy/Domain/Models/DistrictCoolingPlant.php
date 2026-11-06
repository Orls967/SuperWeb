<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class DistrictCoolingPlant extends Model
{
    protected $table = 'egy_district_cooling_plants';

    protected $fillable = [
        'plant_name',
        'plant_code',
        'total_refrigeration_tons',
        'designed_cop_bps',
        'status',
    ];

    protected $casts = [
        'total_refrigeration_tons' => 'integer',
        'designed_cop_bps' => 'integer',
    ];

    public function chillers(): HasMany
    {
        return $this->hasMany(Chiller::class, 'plant_id');
    }

    public function readings(): HasMany
    {
        return $this->hasMany(ChilledWaterReading::class, 'plant_id');
    }
}
