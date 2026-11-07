<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MiningEnvironmentalReading extends Model
{
    use HasUuids;

    protected $table = 'min_environmental_readings';

    protected $fillable = [
        'site_id',
        'location_code',
        'sensor_type',
        'reading_value',
        'unit_of_measure',
        'threshold_limit',
        'is_breached',
        'area_shutdown_triggered',
        'recorded_at',
    ];

    protected $casts = [
        'reading_value' => 'integer',
        'threshold_limit' => 'integer',
        'is_breached' => 'boolean',
        'area_shutdown_triggered' => 'boolean',
        'recorded_at' => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(MiningSite::class, 'site_id');
    }

    public function checkBreachAndShutdown(): bool
    {
        $this->is_breached = $this->reading_value > $this->threshold_limit;
        if ($this->is_breached && in_array($this->sensor_type, ['dust_pm10', 'vibration_pvs', 'water_ph'], true)) {
            $this->area_shutdown_triggered = true;
        }

        return $this->is_breached;
    }
}
