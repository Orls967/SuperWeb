<?php

declare(strict_types=1);

namespace Modules\Mobility\Telematics\Application\Services;

use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Core\Contracts\EventSpineInterface;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Mobility\Telematics\Domain\Events\VehicleAnomalyDetected;
use Modules\Mobility\Telematics\Domain\Models\TelematicsAggregate;
use Modules\Mobility\Telematics\Domain\Models\TelematicsBaseline;
use Modules\Mobility\Telematics\Domain\Models\TelematicsDevice;
use Modules\Mobility\Telematics\Domain\Models\TelematicsTick;
use Modules\Shared\Domain\Algorithms\Stats\RobustStatistics;

class TelematicsIngestService
{
    /**
     * Standard OBD2 DTC codes that require immediate vehicle shutdown / grounding.
     * Non-critical DTCs (e.g. catalyst efficiency P0420, EVAP small leak P0442)
     * are informational warnings only and must NOT ground the vehicle.
     */
    public const array CRITICAL_DTC_CODES = [
        'P0217', // Engine Coolant Overtemperature (critical overheating)
        'P0219', // Engine Overspeed Condition
        'P0300', // Random/Multiple Cylinder Misfire Detected
        'P0301', // Cylinder 1 Misfire Detected
        'P0302', // Cylinder 2 Misfire Detected
        'P0303', // Cylinder 3 Misfire Detected
        'P0304', // Cylinder 4 Misfire Detected
        'P0520', // Engine Oil Pressure Sensor/Switch Circuit Malfunction
        'P0524', // Engine Oil Pressure Too Low (catastrophic engine loss)
        'P0606', // ECM / PCM Processor Fault
        'P0A80', // Replace Hybrid Battery Pack (high voltage risk)
    ];

    public function __construct(
        protected SimClockInterface $simClock,
        protected EventSpineInterface $eventSpine
    ) {}

    public function isCriticalDtc(?string $dtcCode): bool
    {
        if (empty($dtcCode)) {
            return false;
        }

        return in_array(strtoupper(trim($dtcCode)), self::CRITICAL_DTC_CODES, true);
    }

    public function calculateRollingBaseline(int $vehicleId, ?Carbon $asOf = null, ?int $excludeTickId = null): TelematicsBaseline
    {
        $asOf = $asOf ?? Carbon::parse($this->simClock->now());
        $sevenDaysAgo = (clone $asOf)->subDays(7);

        $query = TelematicsTick::where('vehicle_id', $vehicleId)
            ->where('recorded_at', '>=', $sevenDaysAgo)
            ->where('recorded_at', '<=', $asOf);

        if ($excludeTickId !== null) {
            $query->where('id', '!=', $excludeTickId);
        }

        $stats = $query->selectRaw('COUNT(*) as sample_count, AVG(oil_temp_c) as avg_oil_temp, AVG(battery_voltage) as avg_voltage')
            ->first();

        $baseline = TelematicsBaseline::where('vehicle_id', $vehicleId)->first();

        if ($stats && (int) $stats->sample_count > 0) {
            $avgOil = round((float) $stats->avg_oil_temp, 2);
            $avgVolt = round((float) $stats->avg_voltage, 2);
            $count = (int) $stats->sample_count;

            if ($baseline) {
                $baseline->update([
                    'avg_oil_temp_c' => $avgOil,
                    'avg_battery_voltage' => $avgVolt,
                    'sample_count' => $count,
                    'calculated_at' => $asOf,
                ]);
            } else {
                $baseline = TelematicsBaseline::create([
                    'vehicle_id' => $vehicleId,
                    'avg_oil_temp_c' => $avgOil,
                    'avg_battery_voltage' => $avgVolt,
                    'avg_fuel_consumption_rate' => 8.5,
                    'sample_count' => $count,
                    'calculated_at' => $asOf,
                ]);
            }

            return $baseline;
        }

        if ($baseline) {
            return $baseline;
        }

        return TelematicsBaseline::create([
            'vehicle_id' => $vehicleId,
            'avg_oil_temp_c' => 90.0,
            'avg_battery_voltage' => 12.6,
            'avg_fuel_consumption_rate' => 8.5,
            'sample_count' => 0,
            'calculated_at' => $asOf,
        ]);
    }

    public function ingestTick(array $data): TelematicsTick
    {
        $validator = Validator::make($data, [
            'device_id' => ['required', 'string'],
            'sequence' => ['required', 'integer'],
            'recorded_at' => ['required'],
            'oil_temp_c' => ['required', 'numeric'],
            'battery_voltage' => ['required', 'numeric'],
            'fuel_level_pct' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $now = Carbon::parse($this->simClock->now());
        $recordedAt = Carbon::parse($data['recorded_at']);
        if ($recordedAt->lt((clone $now)->subDays(30))) {
            throw new \InvalidArgumentException('Tick rejected: outside acceptable replay window (older than 30 days).');
        }
        if ($recordedAt->gt((clone $now)->addHours(24))) {
            throw new \InvalidArgumentException('Tick rejected: timestamp is too far in the future.');
        }

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
                'oil_temp_c' => (float) $data['oil_temp_c'],
                'battery_voltage' => (float) $data['battery_voltage'],
                'fuel_level_pct' => (float) $data['fuel_level_pct'],
                'dtc_code' => $data['dtc_code'] ?? null,
                'idempotency_key' => $idempotencyKey,
            ]);

            if ($device) {
                $device->last_heartbeat_at = $this->simClock->now();
                if ($this->isCriticalDtc($data['dtc_code'] ?? null)) {
                    $device->status = 'grounded';
                }
                $device->save();
            }

            if ($vehicleId) {
                $this->evaluateAnomalies($vehicleId, $tick);
            }

            $this->aggregateFiveMinuteWindow($data['device_id'], $tick->recorded_at);

            return $tick;
        });
    }

    protected function evaluateAnomalies(int $vehicleId, TelematicsTick $tick): void
    {
        // 7-day rolling baseline calculated from actual historical ticks prior to this tick
        $baseline = $this->calculateRollingBaseline($vehicleId, $tick->recorded_at, $tick->id);

        // Check 1: Oil temperature > 15% above baseline
        if ($tick->oil_temp_c > ($baseline->avg_oil_temp_c * 1.15)) {
            $this->triggerAnomaly($vehicleId, 'OIL_OVERHEAT', 'high', [
                'recorded' => $tick->oil_temp_c,
                'baseline' => $baseline->avg_oil_temp_c,
            ]);
        }

        // Check 2: Critical DTC vs Advisory DTC
        if (! empty($tick->dtc_code)) {
            if ($this->isCriticalDtc($tick->dtc_code)) {
                $this->triggerAnomaly($vehicleId, 'CRITICAL_DTC', 'critical', [
                    'dtc' => $tick->dtc_code,
                ]);
            } else {
                $this->triggerAnomaly($vehicleId, 'DTC_ADVISORY', 'low', [
                    'dtc' => $tick->dtc_code,
                ]);
            }
        }

        // Check 3: Voltage drop below 11.5V
        if ($tick->battery_voltage < 11.5) {
            $this->triggerAnomaly($vehicleId, 'BATTERY_LOW', 'medium', [
                'recorded' => $tick->battery_voltage,
            ]);
        }
    }

    protected function triggerAnomaly(int $vehicleId, string $type, string $severity, array $details): void
    {
        $hourBucket = $this->simClock->now()->format('Y-m-d-H');
        $dedupKey = "anomaly:{$vehicleId}:{$type}:{$severity}:{$hourBucket}";

        DB::afterCommit(function () use ($vehicleId, $type, $severity, $details, $dedupKey) {
            $cacheKey = "telematics:dedup:{$dedupKey}";
            if (! Cache::add($cacheKey, true, 3600)) {
                return;
            }

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
                idempotencyKey: hash('sha256', $dedupKey)
            );

            // 2. Dispatch internal domain event
            event(new VehicleAnomalyDetected($vehicleId, $type, $severity, $details));
        });
    }

    public function aggregateFiveMinuteWindow(string $deviceId, CarbonInterface|string $time): TelematicsAggregate
    {
        $dt = Carbon::parse($time);
        $minuteBucket = (int) (floor($dt->minute / 5) * 5);
        $windowStart = $dt->copy()->minute($minuteBucket)->second(0);
        $windowEnd = (clone $windowStart)->addMinutes(5);

        $stats = TelematicsTick::where('device_id', $deviceId)
            ->where('recorded_at', '>=', $windowStart)
            ->where('recorded_at', '<', $windowEnd)
            ->selectRaw('COUNT(*) as count, AVG(speed_kmh) as avg_speed, MAX(rpm) as max_rpm, AVG(oil_temp_c) as avg_oil, MIN(battery_voltage) as min_volt, MAX(vehicle_id) as vehicle_id')
            ->first();

        $device = TelematicsDevice::where('device_id', $deviceId)->first();
        $vehicleId = $stats?->vehicle_id ?? $device?->vehicle_id;

        return TelematicsAggregate::updateOrCreate(
            [
                'device_id' => $deviceId,
                'window_start' => $windowStart,
            ],
            [
                'vehicle_id' => $vehicleId,
                'window_end' => $windowEnd,
                'avg_speed_kmh' => round((float) ($stats?->avg_speed ?? 0), 2),
                'max_rpm' => (int) ($stats?->max_rpm ?? 0),
                'avg_oil_temp_c' => round((float) ($stats?->avg_oil ?? 90.0), 2),
                'min_battery_voltage' => round((float) ($stats?->min_volt ?? 12.6), 2),
                'tick_count' => (int) ($stats?->count ?? 0),
            ]
        );
    }

    public function canDispatch(object|int $vehicle): bool
    {
        $vehicleId = is_object($vehicle) ? $vehicle->id : (int) $vehicle;

        return ! TelematicsDevice::where('vehicle_id', $vehicleId)
            ->where('status', 'grounded')
            ->exists();
    }

    public function assertCanDispatch(object|int $vehicle): void
    {
        $vehicleId = is_object($vehicle) ? $vehicle->id : (int) $vehicle;
        if (! $this->canDispatch($vehicleId)) {
            throw new \DomainException("Vehicle ID {$vehicleId} is GROUNDED due to critical telematics anomaly/DTC. Fleet dispatch rejected.");
        }
    }

    /**
     * Two-source telematics audit.
     * Cross-checks grounded devices against critical OBD-II DTC diagnostic trouble code ticks.
     */
    public function auditTelematics(): array
    {
        $deviceCount = TelematicsDevice::count();
        $tickCount = TelematicsTick::count();
        $baselineCount = TelematicsBaseline::count();
        $aggregateCount = TelematicsAggregate::count();
        $discrepancies = 0;

        $groundedDevices = TelematicsDevice::where('status', 'grounded')->get();
        foreach ($groundedDevices as $device) {
            $hasCriticalDtc = TelematicsTick::where('device_id', $device->device_id)
                ->whereIn('dtc_code', self::CRITICAL_DTC_CODES)
                ->exists();
            if (! $hasCriticalDtc) {
                $discrepancies++;
            }
        }

        // 1. Cek duplikasi sequence per perangkat
        $duplicateSeqs = DB::table('oto_telematics_ticks')
            ->select('device_id', 'sequence')
            ->groupBy('device_id', 'sequence')
            ->havingRaw('count(*) > 1')
            ->get();
        $discrepancies += $duplicateSeqs->count();

        // 2. Cek integritas baseline vs tick nyata 7 hari
        $baselines = TelematicsBaseline::all();
        $robustStats = new RobustStatistics;
        foreach ($baselines as $b) {
            $windowStart = now()->subDays($b->window_days ?: 7);
            $values = TelematicsTick::where('vehicle_id', $b->vehicle_id)
                ->where('recorded_at', '>=', $windowStart)
                ->whereNotNull($b->metric)
                ->pluck($b->metric)
                ->filter(fn ($v) => is_numeric($v))
                ->map(fn ($v) => (int) $v)
                ->all();

            if (! empty($values)) {
                $expectedMedian = $robustStats->median($values)->toScale(0, RoundingMode::HalfUp)->toInt();
                if ((int) $b->median_value !== $expectedMedian) {
                    $discrepancies++;
                }
            }
        }

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'UNHEALTHY',
            'device_count' => $deviceCount,
            'tick_count' => $tickCount,
            'baseline_count' => $baselineCount,
            'aggregate_count' => $aggregateCount,
            'discrepancies' => $discrepancies,
        ];
    }
}
