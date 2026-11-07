<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Mining\Domain\Enums\FurnaceRunStatus;

class MiningSmelterFurnaceRun extends Model
{
    use HasUuids;

    protected $table = 'min_smelter_furnace_runs';

    protected $fillable = [
        'id',
        'furnace_id',
        'run_code',
        'batch_lot_number',
        'ore_feed_wmt',
        'ore_moisture_pct',
        'ore_feed_dmt',
        'ore_ni_grade_pct',
        'ore_fe_grade_pct',
        'ore_sio2_mgo_ratio',
        'coal_reductant_tons',
        'silica_flux_tons',
        'power_consumption_mwh',
        'tapping_temperature_c',
        'metal_output_tons',
        'metal_recovery_rate_pct',
        'slag_output_tons',
        'started_at',
        'tapped_at',
        'operator_in_charge',
        'hmac_signature',
        'status',
        'metadata',
    ];

    protected $casts = [
        'ore_feed_wmt' => 'float',
        'ore_moisture_pct' => 'float',
        'ore_feed_dmt' => 'float',
        'ore_ni_grade_pct' => 'float',
        'ore_fe_grade_pct' => 'float',
        'ore_sio2_mgo_ratio' => 'float',
        'coal_reductant_tons' => 'float',
        'silica_flux_tons' => 'float',
        'power_consumption_mwh' => 'float',
        'tapping_temperature_c' => 'float',
        'metal_output_tons' => 'float',
        'metal_recovery_rate_pct' => 'float',
        'slag_output_tons' => 'float',
        'started_at' => 'immutable_datetime',
        'tapped_at' => 'immutable_datetime',
        'metadata' => 'array',
    ];

    public function furnace(): BelongsTo
    {
        return $this->belongsTo(MiningSmelterFurnace::class, 'furnace_id');
    }

    public function energyEmissions(): HasMany
    {
        return $this->hasMany(MiningSmelterEnergyEmission::class, 'furnace_run_id');
    }

    public function qualityAssays(): HasMany
    {
        return $this->hasMany(MiningSmelterQualityAssay::class, 'furnace_run_id');
    }

    public function byproducts(): HasMany
    {
        return $this->hasMany(MiningSmelterByproduct::class, 'furnace_run_id');
    }

    public function statusEnum(): FurnaceRunStatus
    {
        return FurnaceRunStatus::tryFrom((string) $this->status) ?? FurnaceRunStatus::DRAFT;
    }
}
