<?php

declare(strict_types=1);

namespace Modules\Wms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kunci eksklusif zona anti-tabrakan & safety interlock manusia-robot (Fase 279).
 */
class RobotZoneLock extends Model
{
    protected $table = 'wms_robot_zone_locks';

    protected $guarded = ['id'];

    protected $casts = [
        'robot_unit_id' => 'integer',
        'human_present' => 'boolean',
        'interlock_active' => 'boolean',
        'speed_limit_mm_s' => 'integer',
        'locked_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function robotUnit(): BelongsTo
    {
        return $this->belongsTo(RobotUnit::class, 'robot_unit_id');
    }
}
