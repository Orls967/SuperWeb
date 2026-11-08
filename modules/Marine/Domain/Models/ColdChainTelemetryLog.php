<?php

declare(strict_types=1);

namespace Modules\Marine\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ColdChainTelemetryLog extends Model
{
    protected $table = 'mar_cold_chain_telemetry_logs';

    protected $fillable = [
        'export_shipment_id',
        'recorded_at',
        'ambient_temperature_celsius',
        'supply_air_temperature_celsius',
        'return_air_temperature_celsius',
        'cargo_probe_temperature_celsius',
        'humidity_percentage',
        'latitude',
        'longitude',
        'compressor_running',
        'defrost_cycle_active',
        'temperature_breach_detected',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(ExportReeferShipment::class, 'export_shipment_id');
    }
}
