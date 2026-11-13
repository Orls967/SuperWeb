<?php

declare(strict_types=1);

namespace Modules\Wms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Wms\Domain\Models\Warehouse;

/**
 * Jadwal pengisian daya armada robot (X279-1).
 */
class RobotChargeSchedule extends Model
{
    protected $table = 'wms_robot_charge_schedules';

    protected $fillable = [
        'warehouse_id',
        'robot_id',
        'window_start_min',
        'window_end_min',
        'initial_battery_pct',
        'target_battery_pct',
        'min_fleet_available',
        'status',
        'idempotency_key',
    ];

    protected $casts = [
        'robot_id' => 'integer',
        'window_start_min' => 'integer',
        'window_end_min' => 'integer',
        'initial_battery_pct' => 'integer',
        'target_battery_pct' => 'integer',
        'min_fleet_available' => 'integer',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function robot(): BelongsTo
    {
        return $this->belongsTo(Robot::class, 'robot_id');
    }
}
