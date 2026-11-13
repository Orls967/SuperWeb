<?php

declare(strict_types=1);

namespace Modules\Wms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Fakta telemetri armada robot simulasi yang bersifat append-only (X279-2, PR5).
 */
class RobotTelemetry extends Model
{
    public $timestamps = false;

    protected $table = 'wms_robot_telemetries';

    protected $fillable = [
        'warehouse_id',
        'robot_id',
        'battery_pct',
        'error_count',
        'downtime_minutes',
        'health_score',
        'anomaly_code',
        'idempotency_key',
        'recorded_at',
    ];

    protected $casts = [
        'robot_id' => 'integer',
        'battery_pct' => 'integer',
        'error_count' => 'integer',
        'downtime_minutes' => 'integer',
        'health_score' => 'integer',
        'recorded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('RobotTelemetry bersifat append-only dan tidak dapat diubah.');
        });
        static::deleting(function (): void {
            throw new LogicException('RobotTelemetry bersifat append-only dan tidak dapat dihapus.');
        });
    }

    public function robot(): BelongsTo
    {
        return $this->belongsTo(Robot::class, 'robot_id');
    }
}
