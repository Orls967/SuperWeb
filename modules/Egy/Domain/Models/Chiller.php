<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Chiller extends Model
{
    protected $table = 'egy_chillers';

    protected $fillable = [
        'plant_id',
        'chiller_number',
        'capacity_tons',
        'rated_power_kw',
        'refrigerant_type',
        'status',
    ];

    protected $casts = [
        'capacity_tons' => 'integer',
        'rated_power_kw' => 'integer',
    ];

    public function plant(): BelongsTo
    {
        return $this->belongsTo(DistrictCoolingPlant::class, 'plant_id');
    }

    public function readings(): HasMany
    {
        return $this->hasMany(ChilledWaterReading::class, 'chiller_id');
    }
}
