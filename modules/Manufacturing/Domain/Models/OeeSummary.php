<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ringkasan OEE per work center per tanggal/shift. */
class OeeSummary extends Model
{
    protected $table = 'mfg_oee_summaries';

    protected $fillable = [
        'work_center_id', 'period_date', 'shift_code', 'planned_minutes', 'run_minutes',
        'ideal_cycle_seconds', 'qty_total', 'qty_good',
        'availability_percent', 'performance_percent', 'quality_percent', 'oee_percent',
        'mtbf_hours', 'mttr_minutes',
    ];

    protected $casts = [
        'period_date' => 'date', 'planned_minutes' => 'integer', 'run_minutes' => 'integer',
        'ideal_cycle_seconds' => 'integer', 'qty_total' => 'decimal:6', 'qty_good' => 'decimal:6',
        'availability_percent' => 'decimal:4', 'performance_percent' => 'decimal:4',
        'quality_percent' => 'decimal:4', 'oee_percent' => 'decimal:4',
        'mtbf_hours' => 'decimal:2', 'mttr_minutes' => 'decimal:2',
    ];

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }
}
