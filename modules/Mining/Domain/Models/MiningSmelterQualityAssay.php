<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MiningSmelterQualityAssay extends Model
{
    use HasUuids;

    protected $table = 'min_smelter_quality_assays';

    protected $fillable = [
        'id',
        'furnace_run_id',
        'assay_number',
        'sample_code',
        'metal_commodity',
        'primary_metal_grade_pct',
        'secondary_metal_grade_pct',
        'iron_fe_pct',
        'carbon_c_pct',
        'silicon_si_pct',
        'phosphorus_p_pct',
        'sulfur_s_pct',
        'moisture_pct',
        'meets_lme_specification',
        'chief_chemist',
        'analyzed_at',
        'hmac_signature',
        'status',
        'metadata',
    ];

    protected $casts = [
        'primary_metal_grade_pct' => 'float',
        'secondary_metal_grade_pct' => 'float',
        'iron_fe_pct' => 'float',
        'carbon_c_pct' => 'float',
        'silicon_si_pct' => 'float',
        'phosphorus_p_pct' => 'float',
        'sulfur_s_pct' => 'float',
        'moisture_pct' => 'float',
        'meets_lme_specification' => 'boolean',
        'analyzed_at' => 'immutable_datetime',
        'metadata' => 'array',
    ];

    public function furnaceRun(): BelongsTo
    {
        return $this->belongsTo(MiningSmelterFurnaceRun::class, 'furnace_run_id');
    }

    public function warehouseReceipts(): HasMany
    {
        return $this->hasMany(MiningMetalsWarehouseReceipt::class, 'assay_id');
    }
}
