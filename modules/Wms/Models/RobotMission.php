<?php

declare(strict_types=1);

namespace Modules\Wms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Misi eksekusi tugas robot dengan state machine 8 status & fallback manual (Fase 279).
 */
class RobotMission extends Model
{
    protected $table = 'wms_robot_missions';

    protected $guarded = ['id'];

    protected $casts = [
        'robot_unit_id' => 'integer',
        'wave_id' => 'integer',
        'fallback_manual' => 'boolean',
        'assigned_operator_user_id' => 'integer',
    ];

    public function robotUnit(): BelongsTo
    {
        return $this->belongsTo(RobotUnit::class, 'robot_unit_id');
    }
}
