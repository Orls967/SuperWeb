<?php

declare(strict_types=1);

namespace Modules\Wms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Log insiden keselamatan simulasi yang menjaga integritas fakta (X279-4, PR5).
 */
class RobotSafetyIncident extends Model
{
    public $timestamps = false;

    protected $table = 'wms_robot_safety_incidents';

    protected $fillable = [
        'warehouse_id',
        'incident_code',
        'mission_id',
        'traffic_zone_id',
        'staff_user_id',
        'severity',
        'incident_type',
        'description',
        'status',
        'simulation_label',
        'reviewed_by_user_id',
        'reviewed_at',
        'recorded_at',
    ];

    protected $casts = [
        'mission_id' => 'integer',
        'traffic_zone_id' => 'integer',
        'staff_user_id' => 'integer',
        'reviewed_by_user_id' => 'integer',
        'reviewed_at' => 'datetime',
        'recorded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            $immutable = ['incident_code', 'warehouse_id', 'mission_id', 'traffic_zone_id', 'staff_user_id', 'severity', 'incident_type', 'description', 'recorded_at'];
            foreach ($immutable as $col) {
                if ($model->isDirty($col)) {
                    throw new LogicException("Kolom fakta {$col} pada RobotSafetyIncident bersifat append-only.");
                }
            }
        });
        static::deleting(function (): void {
            throw new LogicException('RobotSafetyIncident bersifat append-only dan tidak dapat dihapus.');
        });
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(RobotMission::class, 'mission_id');
    }

    public function trafficZone(): BelongsTo
    {
        return $this->belongsTo(TrafficZone::class, 'traffic_zone_id');
    }
}
