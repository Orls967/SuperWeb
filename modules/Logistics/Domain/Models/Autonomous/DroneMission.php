<?php

namespace Modules\Logistics\Domain\Models\Autonomous;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DroneMission extends Model
{
    protected $table = 'lgx_drone_missions';

    protected $guarded = [];

    protected $casts = [
        'delivered_at' => 'datetime',
    ];

    public function drone(): BelongsTo
    {
        return $this->belongsTo(DroneUnit::class, 'drone_unit_id');
    }
}
