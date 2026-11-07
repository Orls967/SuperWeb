<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MiningSmelterFurnace extends Model
{
    use HasUuids;

    protected $table = 'min_smelter_furnaces';

    protected $fillable = [
        'id',
        'site_id',
        'furnace_code',
        'name',
        'furnace_type',
        'rated_capacity_tons_day',
        'transformer_power_mva',
        'operating_temperature_celsius',
        'electrode_diameter_mm',
        'target_metal',
        'status',
        'metadata',
    ];

    protected $casts = [
        'rated_capacity_tons_day' => 'float',
        'transformer_power_mva' => 'float',
        'operating_temperature_celsius' => 'float',
        'electrode_diameter_mm' => 'float',
        'metadata' => 'array',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(MiningSite::class, 'site_id');
    }

    public function furnaceRuns(): HasMany
    {
        return $this->hasMany(MiningSmelterFurnaceRun::class, 'furnace_id');
    }
}
