<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Mall\Domain\Enums\AssetCategory;
use Modules\Mall\Domain\Enums\AssetStatus;
use Modules\Shared\Domain\Traits\HasUuid;

class Asset extends Model
{
    use HasUuid;

    protected $table = 'mall_assets';

    protected $fillable = [
        'uuid',
        'property_id',
        'unit_id',
        'asset_tag',
        'name',
        'category',
        'brand',
        'model_number',
        'serial_number',
        'installation_date',
        'warranty_expiry',
        'status',
        'pm_frequency_days',
        'last_pm_date',
        'next_pm_date',
        'location_notes',
    ];

    protected $casts = [
        'category' => AssetCategory::class,
        'status' => AssetStatus::class,
        'installation_date' => 'date',
        'warranty_expiry' => 'date',
        'pm_frequency_days' => 'integer',
        'last_pm_date' => 'date',
        'next_pm_date' => 'date',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class, 'asset_id');
    }

    public function isPmDue(?Carbon $onDate = null): bool
    {
        $checkDate = $onDate ?? Carbon::today();

        return $this->next_pm_date !== null && $this->next_pm_date->lte($checkDate);
    }
}
