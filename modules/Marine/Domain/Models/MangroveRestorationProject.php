<?php

declare(strict_types=1);

namespace Modules\Marine\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class MangroveRestorationProject extends Model
{
    protected $table = 'mar_mangrove_restoration_projects';

    protected $fillable = [
        'project_code',
        'project_name',
        'coastal_regency',
        'restoration_area_hectares',
        'dominant_mangrove_species',
        'trees_planted_count',
        'survival_rate_percentage',
        'initiated_at',
        'verra_registry_code',
        'status',
    ];

    public function assessments(): HasMany
    {
        return $this->hasMany(BlueCarbonAssessment::class, 'project_id');
    }

    public function issuances(): HasManyThrough
    {
        return $this->hasManyThrough(BlueCarbonCreditIssuance::class, BlueCarbonAssessment::class, 'project_id', 'assessment_id');
    }
}
