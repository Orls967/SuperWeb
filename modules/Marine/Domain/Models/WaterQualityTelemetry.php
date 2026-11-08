<?php

declare(strict_types=1);

namespace Modules\Marine\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaterQualityTelemetry extends Model
{
    protected $table = 'mar_water_quality_telemetries';

    protected $fillable = [
        'batch_id',
        'recorded_at',
        'dissolved_oxygen_mg_l',
        'ph_level',
        'water_temperature_celsius',
        'salinity_ppt',
        'total_ammonia_nitrogen_ppm',
        'nitrite_no2_ppm',
        'alkalinity_mg_l',
        'oxidation_reduction_potential_mv',
        'sensor_device_uuid',
        'aerator_active',
        'water_quality_grade',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AquacultureBatch::class, 'batch_id');
    }
}
