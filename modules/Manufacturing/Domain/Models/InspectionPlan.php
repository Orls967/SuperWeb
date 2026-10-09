<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Rencana inspeksi: karakteristik, batas spesifikasi, AQL simulasi. */
class InspectionPlan extends Model
{
    use HasUuids;

    protected $table = 'mfg_inspection_plans';

    protected $fillable = [
        'name', 'stage', 'characteristics', 'spec_min', 'spec_max', 'aql_percent',
        'sample_size', 'frequency', 'is_active',
    ];

    protected $casts = [
        'characteristics' => 'array', 'spec_min' => 'decimal:6', 'spec_max' => 'decimal:6',
        'aql_percent' => 'decimal:4', 'sample_size' => 'integer', 'is_active' => 'boolean',
    ];

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class, 'plan_id');
    }

    public function spcSamples(): HasMany
    {
        return $this->hasMany(SpcSample::class, 'plan_id');
    }
}
