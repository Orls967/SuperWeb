<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MiningEquipmentTelemetry extends Model
{
    use HasUuids;

    protected $table = 'min_equipment_telemetries';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'equipment_id',
        'sequence_number',
        'recorded_at',
        'engine_hours_total',
        'fuel_level_percent',
        'instant_fuel_rate_ml_s',
        'payload_kg',
        'vibration_rms_scaled',
        'latitude_scaled',
        'longitude_scaled',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'sequence_number' => 'integer',
        'engine_hours_total' => 'integer',
        'fuel_level_percent' => 'integer',
        'instant_fuel_rate_ml_s' => 'integer',
        'payload_kg' => 'integer',
        'vibration_rms_scaled' => 'integer',
        'latitude_scaled' => 'integer',
        'longitude_scaled' => 'integer',
    ];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(MiningEquipment::class, 'equipment_id');
    }
}
