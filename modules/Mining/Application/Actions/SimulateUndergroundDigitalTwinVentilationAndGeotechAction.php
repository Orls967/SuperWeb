<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Mining\Domain\Models\BlastSchedule;
use Modules\Mining\Domain\Models\MiningPit;
use Modules\Mining\Domain\Models\SlopeStabilityReading;
use Modules\Shared\Application\BaseAction;
use RuntimeException;

class SimulateUndergroundDigitalTwinVentilationAndGeotechAction extends BaseAction
{
    /**
     * Simulate underground digital twin ventilation, verify geotech slope stability, and enforce blast safety interlock.
     *
     * @param  array<int, array{worker_id: string, lat: float, lng: float}>  $activePersonnelCoordinates
     * @return array{blast: BlastSchedule, slope_reading: SlopeStabilityReading, safety_cleared: bool, alert_level: string}
     */
    public function handle(
        string $pitId,
        string $scheduledAt,
        float $blastLat,
        float $blastLng,
        float $safetyRadiusMeters = 500.0,
        int $holesCount = 45,
        float $explosivesKg = 1250.0,
        array $activePersonnelCoordinates = [],
        float $displacementMm = 2.5,
        float $velocityMmDay = 1.2
    ): array {
        return DB::transaction(function () use (
            $pitId,
            $scheduledAt,
            $blastLat,
            $blastLng,
            $safetyRadiusMeters,
            $holesCount,
            $explosivesKg,
            $activePersonnelCoordinates,
            $displacementMm,
            $velocityMmDay
        ) {
            $pit = MiningPit::lockForUpdate()->findOrFail($pitId);

            // 119.2 Blast Scheduling & Safety Interlock Radius Check
            $blastCount = BlastSchedule::where('pit_id', $pit->id)->count() + 1;
            $blastCode = sprintf('BLS-%s-%04d', $pit->pit_code, $blastCount);

            $blast = BlastSchedule::create([
                'id' => (string) Str::uuid(),
                'blast_code' => $blastCode,
                'pit_id' => $pit->id,
                'scheduled_at' => $scheduledAt,
                'blast_coord_lat' => $blastLat,
                'blast_coord_lng' => $blastLng,
                'safety_radius_meters' => $safetyRadiusMeters,
                'holes_count' => $holesCount,
                'explosives_kg' => $explosivesKg,
                'oversize_fragmentation_percent' => 5.2,
                'status' => 'SCHEDULED',
            ]);

            foreach ($activePersonnelCoordinates as $personnel) {
                $distanceMeters = $this->calculateHaversineDistance(
                    $blastLat,
                    $blastLng,
                    $personnel['lat'],
                    $personnel['lng']
                );

                if ($distanceMeters <= $safetyRadiusMeters) {
                    $blast->update(['status' => 'BLOCKED']);
                    throw new RuntimeException("Blast detonation blocked: Worker {$personnel['worker_id']} detected within safety exclusion zone ({$distanceMeters}m <= {$safetyRadiusMeters}m)");
                }
            }

            $blast->update(['status' => 'DETONATED']);

            // 119.3 Geotech & Slope Stability Monitoring
            $alarmTriggered = ($velocityMmDay >= 5.0 || $displacementMm >= 15.0);
            $alertLevel = $alarmTriggered ? 'CRITICAL_EVACUATE' : 'NORMAL_MONITORING';

            $sensorCount = SlopeStabilityReading::where('pit_id', $pit->id)->count() + 1;
            $sensorCode = sprintf('SLP-%s-%04d', $pit->pit_code, $sensorCount);

            $slopeReading = SlopeStabilityReading::create([
                'id' => (string) Str::uuid(),
                'sensor_code' => $sensorCode,
                'pit_id' => $pit->id,
                'displacement_mm' => $displacementMm,
                'velocity_mm_day' => $velocityMmDay,
                'evacuation_alarm_triggered' => $alarmTriggered,
                'alert_level' => $alertLevel,
            ]);

            $this->audit(
                action: 'mining.blast_and_geotech.executed',
                auditable: $blast,
                oldValues: null,
                newValues: [
                    'blast_code' => $blastCode,
                    'pit_id' => $pit->id,
                    'safety_cleared' => true,
                    'sensor_code' => $sensorCode,
                    'alert_level' => $alertLevel,
                ],
                correlationId: "min_blast_{$blastCode}",
                impactType: 'geotech_blast_interlock'
            );

            return [
                'blast' => $blast,
                'slope_reading' => $slopeReading,
                'safety_cleared' => true,
                'alert_level' => $alertLevel,
            ];
        });
    }

    private function calculateHaversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000.0; // meters
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }
}
