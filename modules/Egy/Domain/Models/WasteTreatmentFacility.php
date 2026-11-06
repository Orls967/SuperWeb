<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class WasteTreatmentFacility extends Model
{
    protected $table = 'egy_waste_treatment_facilities';

    protected $fillable = [
        'facility_name',
        'facility_code',
        'daily_capacity_tons',
        'biogas_yield_m3_per_ton',
        'effluent_bod_mg_l',
        'effluent_cod_mg_l',
        'status',
    ];

    protected $casts = [
        'daily_capacity_tons' => 'integer',
        'biogas_yield_m3_per_ton' => 'integer',
        'effluent_bod_mg_l' => 'integer',
        'effluent_cod_mg_l' => 'integer',
    ];

    public function intakes(): HasMany
    {
        return $this->hasMany(WasteIntake::class, 'facility_id');
    }

    public function biogasProductions(): HasMany
    {
        return $this->hasMany(BiogasWteProduction::class, 'facility_id');
    }
}
