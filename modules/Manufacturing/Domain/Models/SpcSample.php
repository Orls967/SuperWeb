<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Subgroup SPC X-bar/R. */
class SpcSample extends Model
{
    protected $table = 'mfg_spc_samples';

    protected $fillable = [
        'plan_id', 'inspection_id', 'subgroup', 'mean_value', 'range_value',
        'sample_count', 'in_control', 'taken_at',
    ];

    protected $casts = [
        'inspection_id' => 'integer', 'subgroup' => 'integer',
        'mean_value' => 'decimal:6', 'range_value' => 'decimal:6',
        'sample_count' => 'decimal:2', 'in_control' => 'boolean', 'taken_at' => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(InspectionPlan::class, 'plan_id');
    }
}
