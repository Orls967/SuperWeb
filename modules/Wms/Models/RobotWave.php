<?php

declare(strict_types=1);

namespace Modules\Wms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Wave planning robotik berbasis FFD (J2) per (carrier_code, cutoff_min).
 */
class RobotWave extends Model
{
    protected $table = 'wms_robot_waves';

    protected $fillable = [
        'warehouse_id',
        'wave_code',
        'carrier_code',
        'cutoff_min',
        'capacity',
        'allocated_load',
        'is_oversize',
        'status',
        'order_items',
        'certificate_hash',
    ];

    protected $casts = [
        'cutoff_min' => 'integer',
        'capacity' => 'integer',
        'allocated_load' => 'integer',
        'is_oversize' => 'boolean',
        'order_items' => 'array',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
