<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlopeStabilityReading extends Model
{
    protected $table = 'min_slope_stability_readings';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'sensor_code',
        'pit_id',
        'displacement_mm',
        'velocity_mm_day',
        'evacuation_alarm_triggered',
        'alert_level',
    ];

    protected $casts = [
        'evacuation_alarm_triggered' => 'boolean',
    ];

    public function pit(): BelongsTo
    {
        return $this->belongsTo(MiningPit::class, 'pit_id');
    }
}
