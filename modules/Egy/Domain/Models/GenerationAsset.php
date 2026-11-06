<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GenerationAsset extends Model
{
    use HasUuids;

    protected $table = 'egy_generation_assets';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'asset_code',
        'name',
        'asset_type',
        'installed_capacity_mw',
        'current_output_mw',
        'capacity_mw',
        'available_capacity_mw',
        'min_stable_mw',
        'marginal_cost_idr',
        'marginal_cost_per_mwh_minor',
        'fuel_type',
        'is_must_run',
        'status',
    ];

    protected $casts = [
        'installed_capacity_mw' => 'float',
        'current_output_mw' => 'float',
        'capacity_mw' => 'integer',
        'available_capacity_mw' => 'integer',
        'min_stable_mw' => 'integer',
        'marginal_cost_idr' => 'integer',
        'is_must_run' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->asset_type)) {
                $fuel = strtoupper((string) ($model->fuel_type ?? 'thermal'));
                $model->asset_type = match ($fuel) {
                    'SOLAR' => 'SOLAR_FARM',
                    'COAL' => 'THERMAL_PLTU',
                    'GAS' => 'THERMAL_PLTGU',
                    'DIESEL' => 'GENSET',
                    'BATTERY' => 'BATTERY_BESS',
                    default => 'THERMAL_PLTU',
                };
            }
            if (empty($model->installed_capacity_mw)) {
                $model->installed_capacity_mw = (float) ($model->capacity_mw ?? 100);
            }
            if (! isset($model->current_output_mw)) {
                $model->current_output_mw = 0.0;
            }
            if (empty($model->marginal_cost_per_mwh_minor)) {
                $model->marginal_cost_per_mwh_minor = (int) (($model->marginal_cost_idr ?? 0) * 1000);
            }
            if (empty($model->status)) {
                $model->status = 'ONLINE';
            }
        });
    }

    public function dispatchOrders(): HasMany
    {
        return $this->hasMany(DispatchOrder::class, 'generation_asset_id');
    }
}
