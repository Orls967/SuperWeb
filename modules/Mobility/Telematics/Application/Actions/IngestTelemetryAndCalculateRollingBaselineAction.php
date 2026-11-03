<?php

declare(strict_types=1);

namespace Modules\Mobility\Telematics\Application\Actions;

use Carbon\Carbon;
use Modules\Mobility\Telematics\Domain\Models\TelematicsBaseline;
use Modules\Mobility\Telematics\Domain\Models\TelematicsDevice;
use Modules\Mobility\Telematics\Domain\Models\TelematicsTick;
use Modules\Shared\Application\BaseAction;

class IngestTelemetryAndCalculateRollingBaselineAction extends BaseAction
{
    /**
     * Ingest tick data and recalculate 7-day rolling baseline for a vehicle.
     *
     * @param  array<string, mixed>  $tickData
     */
    public function handle(string $deviceId, int $vehicleId, array $tickData): array
    {
        return $this->transaction(function () use ($deviceId, $vehicleId, $tickData) {
            $sequence = (int) ($tickData['sequence'] ?? ((int) TelematicsTick::where('device_id', $deviceId)->max('sequence') + 1));
            $recordedAt = isset($tickData['recorded_at']) ? Carbon::parse($tickData['recorded_at']) : now();

            $idempotencyKey = hash('sha256', "telematics:baseline:{$deviceId}:{$sequence}:{$recordedAt->timestamp}");

            $tick = TelematicsTick::firstOrCreate(
                ['idempotency_key' => $idempotencyKey],
                [
                    'device_id' => $deviceId,
                    'vehicle_id' => $vehicleId,
                    'sequence' => $sequence,
                    'recorded_at' => $recordedAt,
                    'latitude' => $tickData['latitude'] ?? null,
                    'longitude' => $tickData['longitude'] ?? null,
                    'rpm' => (int) ($tickData['rpm'] ?? 2000),
                    'speed_kmh' => (float) ($tickData['speed_kmh'] ?? 55.0),
                    'oil_temp_c' => (float) ($tickData['oil_temp_c'] ?? 90.0),
                    'battery_voltage' => (float) ($tickData['battery_voltage'] ?? 12.6),
                    'fuel_level_pct' => (float) ($tickData['fuel_level_pct'] ?? 75.0),
                    'dtc_code' => $tickData['dtc_code'] ?? null,
                ]
            );

            // Update device heartbeat
            TelematicsDevice::where('device_id', $deviceId)->update([
                'last_heartbeat_at' => now(),
            ]);

            // Calculate rolling window statistics (past 7 days)
            $since = $recordedAt->copy()->subDays(7);
            $stats = TelematicsTick::where('vehicle_id', $vehicleId)
                ->where('recorded_at', '>=', $since)
                ->where('recorded_at', '<=', $recordedAt)
                ->selectRaw('COUNT(*) as total_samples, AVG(oil_temp_c) as avg_oil, AVG(battery_voltage) as avg_volt, AVG(fuel_level_pct) as avg_fuel')
                ->first();

            $sampleCount = (int) ($stats?->total_samples ?? 1);
            $avgOil = round((float) ($stats?->avg_oil ?? $tick->oil_temp_c), 2);
            $avgVolt = round((float) ($stats?->avg_volt ?? $tick->battery_voltage), 2);
            $avgFuel = round((float) ($stats?->avg_fuel ?? $tick->fuel_level_pct), 2);

            $baseline = TelematicsBaseline::updateOrCreate(
                ['vehicle_id' => $vehicleId],
                [
                    'avg_oil_temp_c' => $avgOil,
                    'avg_battery_voltage' => $avgVolt,
                    'avg_fuel_consumption_rate' => $avgFuel,
                    'sample_count' => $sampleCount,
                    'calculated_at' => now(),
                ]
            );

            $this->audit(
                action: 'telematics.baseline.calculated',
                auditable: $baseline,
                oldValues: null,
                newValues: [
                    'vehicle_id' => $vehicleId,
                    'avg_oil_temp_c' => $avgOil,
                    'avg_battery_voltage' => $avgVolt,
                    'sample_count' => $sampleCount,
                ],
                correlationId: "tel_base_{$vehicleId}_{$baseline->id}",
                impactType: 'telematics'
            );

            return [
                'tick' => $tick,
                'baseline' => $baseline,
                'sample_count' => $sampleCount,
            ];
        });
    }
}
