<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MiningBlastFragmentationEval extends Model
{
    use HasUuids;

    protected $table = 'min_blast_fragmentation_evals';

    protected $fillable = [
        'id',
        'blast_schedule_id',
        'blast_clearance_run_id',
        'eval_code',
        'mean_fragment_size_d50_mm',
        'median_size_x50_mm',
        'passing_80_percent_d80_mm',
        'uniformity_index_n',
        'characteristic_size_xc_cm',
        'powder_factor_kg_m3',
        'oversize_percent',
        'boulder_oversize_percent',
        'fines_percent',
        'fines_undersize_percent',
        'estimated_boulder_count',
        'secondary_breaking_needed',
        'burden_adjustment_m',
        'spacing_adjustment_m',
        'powder_factor_adjustment_kg_m3',
        'status',
        'hmac_signature',
        'metadata',
    ];

    protected $casts = [
        'mean_fragment_size_d50_mm' => 'float',
        'median_size_x50_mm' => 'float',
        'passing_80_percent_d80_mm' => 'float',
        'uniformity_index_n' => 'float',
        'characteristic_size_xc_cm' => 'float',
        'powder_factor_kg_m3' => 'float',
        'oversize_percent' => 'float',
        'boulder_oversize_percent' => 'float',
        'fines_percent' => 'float',
        'fines_undersize_percent' => 'float',
        'estimated_boulder_count' => 'integer',
        'secondary_breaking_needed' => 'boolean',
        'burden_adjustment_m' => 'float',
        'spacing_adjustment_m' => 'float',
        'powder_factor_adjustment_kg_m3' => 'float',
        'metadata' => 'array',
    ];

    public function blastRun(): BelongsTo
    {
        return $this->belongsTo(MiningBlastClearanceRun::class, 'blast_clearance_run_id');
    }
}
