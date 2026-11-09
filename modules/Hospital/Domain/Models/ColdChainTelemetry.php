<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ColdChainTelemetry extends Model
{
    protected $table = 'hsp_cold_chain_telemetries';

    protected $fillable = [
        'sensor_device_code',
        'fridge_unit_code',
        'storage_zone',
        'temperature_c',
        'humidity_pct',
        'is_excursion',
        'excursion_severity',
        'recorded_at',
    ];

    protected $casts = [
        'temperature_c' => 'float',
        'humidity_pct' => 'float',
        'is_excursion' => 'boolean',
        'recorded_at' => 'datetime',
    ];

    /**
     * Memeriksa apakah suhu berada dalam rentang aman sesuai zona penyimpanan.
     */
    public static function evaluateExcursion(string $zone, float $tempC): array
    {
        return match ($zone) {
            'CHILLER_2_TO_8' => [
                'is_excursion' => $tempC < 2.0 || $tempC > 8.0,
                'severity' => ($tempC < 0.0 || $tempC > 12.0) ? 'CRITICAL' : (($tempC < 2.0 || $tempC > 8.0) ? 'WARNING' : 'NORMAL'),
            ],
            'FREEZER_MINUS_20' => [
                'is_excursion' => $tempC > -15.0,
                'severity' => $tempC > -10.0 ? 'CRITICAL' : ($tempC > -15.0 ? 'WARNING' : 'NORMAL'),
            ],
            'ULTRA_LOW_MINUS_80' => [
                'is_excursion' => $tempC > -60.0,
                'severity' => $tempC > -50.0 ? 'CRITICAL' : ($tempC > -60.0 ? 'WARNING' : 'NORMAL'),
            ],
            default => [
                'is_excursion' => $tempC < 15.0 || $tempC > 25.0,
                'severity' => ($tempC < 10.0 || $tempC > 30.0) ? 'CRITICAL' : (($tempC < 15.0 || $tempC > 25.0) ? 'WARNING' : 'NORMAL'),
            ],
        };
    }
}
