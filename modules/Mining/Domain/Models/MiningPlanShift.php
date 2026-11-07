<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MiningPlanShift extends Model
{
    use HasUuids;

    protected $table = 'min_mine_plan_shifts';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'mine_plan_id',
        'pit_id',
        'shift_date',
        'shift_code',
        'target_tonnage_kg',
        'actual_tonnage_kg',
        'effective_hours_nominal',
        'effective_hours_adjusted',
        'rain_downtime_hours',
        'maintenance_downtime_hours',
        'variance_kg',
    ];

    protected $casts = [
        'shift_date' => 'date',
        'target_tonnage_kg' => 'integer',
        'actual_tonnage_kg' => 'integer',
        'effective_hours_nominal' => 'integer',
        'effective_hours_adjusted' => 'integer',
        'rain_downtime_hours' => 'integer',
        'maintenance_downtime_hours' => 'integer',
        'variance_kg' => 'integer',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MiningPlan::class, 'mine_plan_id');
    }

    public function pit(): BelongsTo
    {
        return $this->belongsTo(MiningPit::class, 'pit_id');
    }
}
