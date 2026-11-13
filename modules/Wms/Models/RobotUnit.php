<?php

declare(strict_types=1);

namespace Modules\Wms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Unit robot AMR / conveyor di gudang (Fase 279).
 */
class RobotUnit extends Model
{
    protected $table = 'wms_robot_units';

    protected $guarded = ['id'];

    protected $casts = [
        'battery_pct' => 'integer',
        'max_speed_mm_s' => 'integer',
    ];

    public function missions(): HasMany
    {
        return $this->hasMany(RobotMission::class, 'robot_unit_id');
    }
}
