<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MiningDispatchAssignment extends Model
{
    protected $table = 'min_dispatch_assignments';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'dispatch_plan_id',
        'truck_id',
        'shovel_slot_id',
        'estimated_cycle_seconds',
        'is_override',
        'override_reason',
        'override_by_user_id',
    ];

    protected $casts = [
        'estimated_cycle_seconds' => 'integer',
        'is_override' => 'boolean',
        'override_by_user_id' => 'integer',
    ];

    public function plan()
    {
        return $this->belongsTo(MiningDispatchPlan::class, 'dispatch_plan_id');
    }

    public function truck()
    {
        return $this->belongsTo(MiningEquipment::class, 'truck_id');
    }

    public function shovelSlot()
    {
        return $this->belongsTo(MiningShovelSlot::class, 'shovel_slot_id');
    }
}
