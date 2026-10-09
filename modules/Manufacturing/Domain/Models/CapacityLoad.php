<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Load vs available minutes per work center and period (CRP). */
class CapacityLoad extends Model
{
    protected $table = 'mfg_capacity_loads';

    protected $fillable = [
        'run_id', 'work_center_id', 'period_start', 'load_minutes',
        'capacity_minutes', 'utilization_percent', 'bottleneck',
    ];

    protected $casts = [
        'period_start' => 'date', 'load_minutes' => 'integer',
        'capacity_minutes' => 'integer', 'utilization_percent' => 'decimal:2',
        'bottleneck' => 'boolean',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(MrpRun::class, 'run_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }
}
