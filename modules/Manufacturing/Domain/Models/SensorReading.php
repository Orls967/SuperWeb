<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bacaan sensor IoT simulasi: ambang → alarm → WO otomatis (idempoten). */
class SensorReading extends Model
{
    protected $table = 'mfg_sensor_readings';

    protected $fillable = [
        'work_center_id', 'sensor_code', 'metric', 'value', 'unit',
        'threshold_high', 'threshold_low', 'alarm', 'maintenance_order_id', 'recorded_at',
    ];

    protected $casts = [
        'value' => 'decimal:6', 'threshold_high' => 'decimal:6', 'threshold_low' => 'decimal:6',
        'alarm' => 'boolean', 'recorded_at' => 'datetime',
    ];

    public const METRICS = ['temperature', 'vibration', 'current'];

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }

    public function breachesThreshold(): bool
    {
        if ($this->threshold_high !== null && (float) $this->value > (float) $this->threshold_high) {
            return true;
        }

        return $this->threshold_low !== null && (float) $this->value < (float) $this->threshold_low;
    }
}
