<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MiningUndergroundStope extends Model
{
    use HasUuids;

    protected $table = 'min_underground_stopes';

    protected $fillable = [
        'id',
        'site_id',
        'pit_id',
        'stope_code',
        'name',
        'level_name',
        'mining_method',
        'level_depth_m',
        'rock_mass_rating',
        'length_m',
        'width_m',
        'height_m',
        'length_meters',
        'width_meters',
        'height_meters',
        'volume_m3',
        'planned_tonnage',
        'actual_tonnage',
        'extracted_tonnage',
        'ore_grade',
        'support_system',
        'status',
        'metadata',
    ];

    protected $casts = [
        'level_depth_m' => 'integer',
        'rock_mass_rating' => 'integer',
        'length_m' => 'float',
        'width_m' => 'float',
        'height_m' => 'float',
        'length_meters' => 'float',
        'width_meters' => 'float',
        'height_meters' => 'float',
        'volume_m3' => 'float',
        'planned_tonnage' => 'float',
        'actual_tonnage' => 'float',
        'extracted_tonnage' => 'float',
        'ore_grade' => 'float',
        'metadata' => 'array',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(MiningSite::class, 'site_id');
    }

    public function pit(): BelongsTo
    {
        return $this->belongsTo(MiningPit::class, 'pit_id');
    }

    public function shafts(): HasMany
    {
        return $this->hasMany(MiningVentilationShaft::class, 'stope_id');
    }
}
