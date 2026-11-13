<?php

declare(strict_types=1);

namespace Modules\Wms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Riwayat transisi state machine RobotMission yang bersifat append-only (PR5).
 */
class RobotMissionEvent extends Model
{
    public $timestamps = false;

    protected $table = 'wms_robot_mission_events';

    protected $fillable = [
        'mission_id',
        'from_status',
        'to_status',
        'actor_user_id',
        'reason_code',
        'reason_note',
        'metadata',
        'recorded_at',
    ];

    protected $casts = [
        'mission_id' => 'integer',
        'actor_user_id' => 'integer',
        'metadata' => 'array',
        'recorded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('RobotMissionEvent bersifat append-only dan tidak dapat diubah.');
        });
        static::deleting(function (): void {
            throw new LogicException('RobotMissionEvent bersifat append-only dan tidak dapat dihapus.');
        });
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(RobotMission::class, 'mission_id');
    }
}
