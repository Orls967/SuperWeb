<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlantArea extends Model
{
    protected $table = 'mfg_plant_areas';

    protected $fillable = [
        'plant_id', 'parent_id', 'code', 'name', 'kind',
        'nominal_capacity_per_day', 'capacity_uom', 'is_active',
    ];

    protected $casts = ['nominal_capacity_per_day' => 'integer', 'is_active' => 'boolean'];

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
