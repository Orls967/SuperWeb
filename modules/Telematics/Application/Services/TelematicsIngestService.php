<?php

declare(strict_types=1);

namespace Modules\Telematics\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\EventSpineInterface;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Telematics\Domain\Events\VehicleAnomalyDetected;
use Modules\Telematics\Domain\Models\TelematicsBaseline;
use Modules\Telematics\Domain\Models\TelematicsDevice;
use Modules\Telematics\Domain\Models\TelematicsTick;

class TelematicsIngestService
{
    public function __construct(
        protected SimClockInterface $simClock,
        protected EventSpineInterface $eventSpine
    ) {}

    public function ingestTick(array $data): TelematicsTick
    {
        $idempotencyKey = $data['idempotency_key'] ?? hash('sha256', $data['device_id'].':'.$data['sequence'].':'.$data['recorded_at']);

        return DB::transaction(function () use ($data, $idempotencyKey) {
            $existing = TelematicsTick::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }

            $device = TelematicsDevice::where('device_id', $data['device_id'])->first();
            $vehicleId = $device?->vehicle_id ?? $data['vehicle_id'] ?? null;

            $tick = TelematicsTick::create([
                'device_id' => $data['device_id'],
                'vehicle_id' => $vehicleId,
                'sequence' => $data['sequence'],
                'recorded_at' => Carbon::parse($data['recorded_at']),
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'rpm' => $data['rpm'] ?? 0,
                'speed_kmh' => $data['speed_kmh'] ?? 0,
                'oil_temp_c' => $data['oil_temp_c'] ?? 90.0,
                'battery_voltage' => $data['battery_voltage'] ?? 12.6,
                'fuel_level_pct' => $data['fuel_level_pct'] ?? 100.0,
                'dtc_code' => $data['dtc_code'] ?? null,
                'idempotency_key' => $idempotencyKey,
            ]);

            if ($device) {
                $device->last_heartbeat_at = $this->simClock->now();
                if (! empty($data['dtc_code']) && str_starts_with($data['dtc_code'], 'P0')) {
                    $device->status = 'grounded';
                }
                $device->save();
            }

            if ($vehicleId) {
                $this->evaluateAnomalies($vehicleId, $tick);
            }

            return $tick;
        });
    }

    protected function evaluateAnomalies(int $vehicleId, TelematicsTick $tick): void
    {
        $baseline = TelematicsBaseline::firstOrCreate(
            ['vehicle_id' => $vehicleId],
            [
                'avg_oil_temp_c' => 90.0,
                'avg_battery_voltage' => 12.6,
                'avg_fuel_consumption_rate' => 8.5,
                'sample_count' => 10,
                'calculated_at' => $this->simClock->now(),
            ]
        );

        // Check 1: Oil temperature > 15% above baseline
        if ($tick->oil_temp_c > ($baseline->avg_oil_temp_c * 1.15)) {
            $this->triggerAnomaly($vehicleId, 'OIL_OVERHEAT', 'high', [
                'recorded' => $tick->oil_temp_c,
                'baseline' => $baseline->avg_oil_temp_c,
            ]);
        }

        // Check 2: Critical DTC
        if (! empty($tick->dtc_code)) {
            $this->triggerAnomaly($vehicleId, 'CRITICAL_DTC', 'critical', [
                'dtc' => $tick->dtc_code,
            ]);
        }

        // Check 3: Voltage drop
        if ($tick->battery_voltage < 11.5) {
            $this->triggerAnomaly($vehicleId, 'BATTERY_LOW', 'medium', [
                'recorded' => $tick->battery_voltage,
            ]);
        }
    }

    protected function triggerAnomaly(int $vehicleId, string $type, string $severity, array $details): void
    {
        // 1. Dispatch event via EventSpine
        $this->eventSpine->publish(
            topic: 'auto.telematics',
            eventName: 'VehicleAnomalyDetected',
            payload: [
                'vehicle_id' => $vehicleId,
                'type' => $type,
                'severity' => $severity,
                'details' => $details,
            ],
            idempotencyKey: hash('sha256', "anomaly:{$vehicleId}:{$type}:{$severity}:{$this->simClock->now()->format('Y-m-d-H')}")
        );

        // 2. Dispatch internal domain event
        event(new VehicleAnomalyDetected($vehicleId, $type, $severity, $details));
    }
}
