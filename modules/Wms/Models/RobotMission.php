<?php

declare(strict_types=1);

namespace Modules\Wms\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Wms\Domain\Models\Warehouse;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Misi eksekusi tugas robot dengan state machine 8 status & fallback manual (Fase 279).
 */
class RobotMission extends Model
{
    protected $table = 'wms_robot_missions';

    protected $fillable = [
        'mission_code',
        'warehouse_id',
        'robot_unit_id',
        'robot_id',
        'wave_id',
        'warehouse_task_id',
        'zone_code',
        'traffic_zone_id',
        'status',
        'fallback_manual',
        'retry_count',
        'priority',
        'scheduled_start_min',
        'duration_min',
        'assigned_operator_user_id',
        'failure_reason',
        'certificate_hash',
    ];

    protected $casts = [
        'robot_unit_id' => 'integer',
        'robot_id' => 'integer',
        'wave_id' => 'integer',
        'warehouse_task_id' => 'integer',
        'traffic_zone_id' => 'integer',
        'fallback_manual' => 'boolean',
        'retry_count' => 'integer',
        'priority' => 'integer',
        'scheduled_start_min' => 'integer',
        'duration_min' => 'integer',
        'assigned_operator_user_id' => 'integer',
    ];

    public function robotUnit(): BelongsTo
    {
        return $this->belongsTo(RobotUnit::class, 'robot_unit_id');
    }

    public function robot(): BelongsTo
    {
        return $this->belongsTo(Robot::class, 'robot_id');
    }

    public function trafficZone(): BelongsTo
    {
        return $this->belongsTo(TrafficZone::class, 'traffic_zone_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(RobotMissionEvent::class, 'mission_id')->orderBy('recorded_at');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(ZoneReservation::class, 'mission_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
