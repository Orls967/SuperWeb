<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Satu run perencanaan MRP/CRP; run_key mengunci input dan parameter. */
class MrpRun extends Model
{
    use HasUuids;

    protected $table = 'mfg_mrp_runs';

    protected $fillable = [
        'run_key', 'status', 'is_scenario', 'horizon_days', 'bucket_days',
        'params', 'summary', 'started_at', 'finished_at', 'error',
    ];

    protected $casts = [
        'is_scenario' => 'boolean', 'horizon_days' => 'integer',
        'bucket_days' => 'integer', 'params' => 'array', 'summary' => 'array',
        'started_at' => 'datetime', 'finished_at' => 'datetime',
    ];

    public function requirements(): HasMany
    {
        return $this->hasMany(MrpRequirement::class, 'run_id');
    }

    public function plannedOrders(): HasMany
    {
        return $this->hasMany(PlannedOrder::class, 'run_id');
    }

    public function capacityLoads(): HasMany
    {
        return $this->hasMany(CapacityLoad::class, 'run_id');
    }
}
