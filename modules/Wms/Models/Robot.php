<?php

declare(strict_types=1);

namespace Modules\Wms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Wms\Domain\Models\Warehouse;

/**
 * Registri armada robot gudang simulasi (wms_robots, Fase 279).
 */
class Robot extends Model
{
    protected $table = 'wms_robots';

    protected $fillable = [
        'robot_code',
        'warehouse_id',
        'robot_type',
        'capacity_units',
        'battery_pct',
        'is_online',
        'position_x',
        'position_y',
        'current_zone_code',
        'device_mode',
        'health_score',
    ];

    protected $casts = [
        'capacity_units' => 'integer',
        'battery_pct' => 'integer',
        'is_online' => 'boolean',
        'position_x' => 'integer',
        'position_y' => 'integer',
        'health_score' => 'integer',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function missions(): HasMany
    {
        return $this->hasMany(RobotMission::class, 'robot_id');
    }

    public function chargeSchedules(): HasMany
    {
        return $this->hasMany(RobotChargeSchedule::class, 'robot_id');
    }

    public function telemetries(): HasMany
    {
        return $this->hasMany(RobotTelemetry::class, 'robot_id')->orderByDesc('recorded_at');
    }
}
