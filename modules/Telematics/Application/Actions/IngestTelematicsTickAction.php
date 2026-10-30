<?php

declare(strict_types=1);

namespace Modules\Telematics\Application\Actions;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Telematics\Domain\Events\TelematicsAnomalyDetectedEvent;
use Modules\Telematics\Domain\Models\TelematicsAnomaly;
use Modules\Telematics\Domain\Models\TelematicsBaseline;
use Modules\Telematics\Domain\Models\TelematicsDevice;
use Modules\Telematics\Domain\Models\TelematicsSequenceGap;
use Modules\Telematics\Domain\Models\TelematicsTick;

class IngestTelematicsTickAction
{
    private const CRITICAL_DTC_CODES = [
        'P0300', // Random/Multiple Cylinder Misfire Detected
        'P0117', // Engine Coolant Temperature Sensor 1 Circuit Low
        'P0562', // System Voltage Low
        'P0217', // Engine Coolant Over Temperature Condition
        'P0219', // Engine Overspeed Condition
    ];

    public function __construct(
        private readonly RecomputeRobustBaselineAction $baselineAction
    ) {}

    public function execute(
        TelematicsDevice $device,
        string $token,
        int $sequence,
        CarbonInterface|DateTimeInterface $recordedAt,
        ?int $latitudeE6 = null,
        ?int $longitudeE6 = null,
        int $rpm = 0,
        float|int $speedKmh = 0,
        ?int $coolantTempDeciC = null,
        ?int $batteryMv = null,
        ?int $fuelPermil = null,
        ?int $odometerMeters = null,
        array $dtcCodes = []
    ): TelematicsTick {
        // 1. Verifikasi Autentikasi Token
        if ($device->token_hash !== null) {
            $expectedHash = hash('sha256', $token);
            if (! hash_equals($device->token_hash, $expectedHash)) {
                throw new \InvalidArgumentException('Invalid device token');
            }
        }

        // 2. Deteksi Celah Sequence
        $lastSeq = (int) TelematicsTick::where('device_id', $device->device_id)->max('sequence');
        if ($lastSeq > 0 && $sequence > $lastSeq + 1) {
            TelematicsSequenceGap::firstOrCreate([
                'device_id' => $device->device_id,
                'expected_sequence' => $lastSeq + 1,
                'received_sequence' => $sequence,
            ], [
                'detected_at' => now(),
            ]);
        }

        // 3. Simpan Tick secara Idempoten (PR5 Append-Only)
        $idempotencyKey = hash('sha256', "telematics:tick:{$device->device_id}:{$sequence}");

        $tick = TelematicsTick::firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'device_id' => $device->device_id,
                'vehicle_id' => $device->vehicle_id,
                'sequence' => $sequence,
                'recorded_at' => $recordedAt,
                'latitude_e6' => $latitudeE6,
                'longitude_e6' => $longitudeE6,
                'rpm' => $rpm,
                'speed_kmh' => $speedKmh,
                'coolant_temp_deci_c' => $coolantTempDeciC,
                'battery_mv' => $batteryMv,
                'fuel_permil' => $fuelPermil,
                'odometer_meters' => $odometerMeters,
                'dtc_codes_json' => ! empty($dtcCodes) ? $dtcCodes : null,
                'dtc_code' => ! empty($dtcCodes) ? implode(',', $dtcCodes) : null,
            ]
        );

        // 4. Evaluasi DTC Kritis -> Grounding Unit
        $criticalFound = array_intersect($dtcCodes, self::CRITICAL_DTC_CODES);
        if (! empty($criticalFound)) {
            $device->update(['status' => 'GROUNDED']);
            if ($device->vehicle_id !== null) {
                Vehicle::where('id', $device->vehicle_id)->update(['status' => 'grounded']);
            }
        }

        // 5. Evaluasi Anomali Berbasis Baseline
        if ($device->vehicle_id !== null) {
            $dateStr = Carbon::parse($recordedAt)->toDateString();
            $metricsToCheck = [
                'coolant_temp_deci_c' => $coolantTempDeciC,
                'battery_mv' => $batteryMv,
                'fuel_permil' => $fuelPermil,
            ];

            foreach ($metricsToCheck as $metric => $val) {
                if ($val === null) {
                    continue;
                }

                $baseline = TelematicsBaseline::where('vehicle_id', $device->vehicle_id)
                    ->where('metric', $metric)
                    ->first();

                if ($baseline !== null && $this->baselineAction->isAnomaly($metric, $val, $baseline)) {
                    $wasCreated = false;
                    $anomaly = TelematicsAnomaly::firstOrCreate([
                        'vehicle_id' => $device->vehicle_id,
                        'metric' => $metric,
                        'anomaly_date' => $dateStr,
                    ], [
                        'observed_value' => $val,
                        'threshold_value' => $baseline->median_value,
                    ]);

                    if ($anomaly->wasRecentlyCreated) {
                        event(new TelematicsAnomalyDetectedEvent(
                            vehicleId: $device->vehicle_id,
                            metric: $metric,
                            observedValue: $val,
                            thresholdValue: $baseline->median_value,
                            anomalyDate: $dateStr
                        ));
                    }
                }
            }
        }

        // 6. Update Heartbeat
        $device->update(['last_heartbeat_at' => $recordedAt]);

        return $tick;
    }
}
