<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MiningFuelStorage extends Model
{
    use HasUuids;

    protected $table = 'min_fuel_storages';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'site_id',
        'storage_code',
        'name',
        'type',
        'capacity_ml',
        'current_stock_ml',
        'minimum_safety_stock_ml',
        'status',
    ];

    protected $casts = [
        'capacity_ml' => 'integer',
        'current_stock_ml' => 'integer',
        'minimum_safety_stock_ml' => 'integer',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(MiningSite::class, 'site_id');
    }

    public function dispenses(): HasMany
    {
        return $this->hasMany(MiningFuelDispenseRecord::class, 'storage_id');
    }
}

