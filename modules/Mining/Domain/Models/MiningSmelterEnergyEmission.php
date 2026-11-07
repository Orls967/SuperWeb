<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MiningSmelterEnergyEmission extends Model
{
    use HasUuids;

    protected $table = 'min_smelter_energy_emissions';

    protected $fillable = [
        'id',
        'furnace_run_id',
        'emission_code',
        'electricity_mwh',
        'electricity_emission_factor_tco2_mwh',
        'coal_burned_tons',
        'coal_emission_factor_tco2_ton',
        'diesel_fuel_liters',
        'scope1_emissions_tco2e',
        'scope2_emissions_tco2e',
        'total_emissions_tco2e',
        'intensity_tco2e_per_ton_metal',
        'carbon_tax_threshold_exceeded',
        'status',
        'metadata',
    ];

    protected $casts = [
        'electricity_mwh' => 'float',
        'electricity_emission_factor_tco2_mwh' => 'float',
        'coal_burned_tons' => 'float',
        'coal_emission_factor_tco2_ton' => 'float',
        'diesel_fuel_liters' => 'float',
        'scope1_emissions_tco2e' => 'float',
        'scope2_emissions_tco2e' => 'float',
        'total_emissions_tco2e' => 'float',
        'intensity_tco2e_per_ton_metal' => 'float',
        'carbon_tax_threshold_exceeded' => 'boolean',
        'metadata' => 'array',
    ];

    public function furnaceRun(): BelongsTo
    {
        return $this->belongsTo(MiningSmelterFurnaceRun::class, 'furnace_run_id');
    }
}
