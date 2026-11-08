<?php

declare(strict_types=1);

namespace Modules\Marine\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlueCarbonAssessment extends Model
{
    protected $table = 'mar_blue_carbon_assessments';

    protected $fillable = [
        'assessment_number',
        'project_id',
        'survey_date',
        'above_ground_biomass_tonnes_per_ha',
        'below_ground_biomass_tonnes_per_ha',
        'soil_organic_carbon_tonnes_per_ha',
        'total_carbon_stock_tco2e_per_ha',
        'annual_sequestration_rate_tco2e_yr',
        'buffer_pool_deduction_pct',
        'net_creditable_carbon_tonnes_co2e',
        'third_party_auditor_name',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(MangroveRestorationProject::class, 'project_id');
    }

    public function issuances(): HasMany
    {
        return $this->hasMany(BlueCarbonCreditIssuance::class, 'assessment_id');
    }
}
