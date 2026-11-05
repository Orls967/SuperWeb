<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Insurance\Domain\Enums\CatRunStatus;

class CatRun extends Model
{
    protected $table = 'ins_cat_runs';

    /** @var list<string> */
    protected $fillable = [
        'run_code',
        'portfolio_code',
        'seed',
        'years',
        'events_per_peril',
        'parameter_versions',
        'catalog_hash',
        'exposure_hash',
        'total_exposure_idr',
        'aal_idr',
        'aep_rp100_idr',
        'aep_rp250_idr',
        'oep_rp100_idr',
        'tvar_rp100_idr',
        'status',
        'failure_reason',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'parameter_versions' => 'array',
            'status' => CatRunStatus::class,
        ];
    }

    /** @return HasMany<CatExposure, $this> */
    public function exposures(): HasMany
    {
        return $this->hasMany(CatExposure::class, 'run_id')->orderBy('region_code')->orderBy('peril');
    }

    /** @return HasMany<CatEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(CatEvent::class, 'run_id')->orderBy('event_no');
    }

    /** @return HasMany<CatYearLoss, $this> */
    public function yearLosses(): HasMany
    {
        return $this->hasMany(CatYearLoss::class, 'run_id')->orderBy('year_index');
    }

    /** @return HasMany<CatPlan, $this> */
    public function plans(): HasMany
    {
        return $this->hasMany(CatPlan::class, 'run_id')->orderBy('structure_code');
    }
}
