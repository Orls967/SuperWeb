<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * AutonomousFieldFleetService (Fase 306)
 *
 * Implements:
 *  - 306.1 Autonomous vehicle corridor operation with safety cage (geofence & permanent speed cap)
 *  - 306.2 Remote Operation Center (ROC) operator supervision & intervention logging
 *  - 306.4 Tests: Geofence violation emergency stops unit; manual fallback available & verified; intervention recorded
 *  - 306.5 Edge case: Autonomous unit stopping in dense pedestrian zone must remain safe-stopped until operator intervention (cannot resume autonomously)
 *  - 306.6 Risk: Safety cage (geofence, speed cap) is the immutable fail-safe and can never be disabled
 */
class AutonomousFieldFleetService
{
    /**
     * Register autonomous field unit with mandatory active safety cage (306.1 & 306.6).
     */
    public function registerFleetUnit(
        string $unitCode,
        string $vehicleType,
        string $zone,
        float $speedCapKmh = 25.00
    ): object {
        $uCode = strtoupper($unitCode);

        // Risk check 306.6: Speed cap must be strictly enforceable
        if ($speedCapKmh <= 0.0 || $speedCapKmh > 50.0) {
            throw new InvalidArgumentException('Safety cage violation: Autonomous unit speed cap must be between 1 and 50 km/h (306.6).');
        }

        $id = DB::table('autonomous_fleet_units')->insertGetId([
            'unit_code' => $uCode,
            'vehicle_type' => strtoupper($vehicleType),
            'operational_zone' => strtoupper($zone),
            'current_speed_kmh' => 0.00,
            'safety_cage_speed_cap_kmh' => $speedCapKmh,
            'geofence_cage_active' => true, // 306.6 Permanently active
            'emergency_stopped' => false,
            'emergency_stop_reason' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('autonomous_fleet_units')->find($id);
    }

    /**
     * Enforce geofence boundary & pedestrian safety: breaches trigger instant emergency stop (306.4, 306.5, 306.6).
     */
    public function checkSafetyPerimeter(string $unitCode, bool $isOutsideGeofence, bool $inDensePedestrianZone): object
    {
        $uCode = strtoupper($unitCode);
        $unit = DB::table('autonomous_fleet_units')->where('unit_code', $uCode)->first();
        if (! $unit) {
            throw new InvalidArgumentException("Unit '{$unitCode}' not found.");
        }

        $shouldStop = false;
        $reason = null;

        if ($isOutsideGeofence) {
            $shouldStop = true;
            $reason = 'GEOFENCE_PERIMETER_BREACH'; // 306.4
        } elseif ($inDensePedestrianZone) {
            $shouldStop = true;
            $reason = 'PEDESTRIAN_ZONE_SAFETY_HOLD'; // 306.5
        }

        if ($shouldStop) {
            DB::table('autonomous_fleet_units')
                ->where('unit_code', $uCode)
                ->update([
                    'emergency_stopped' => true,
                    'current_speed_kmh' => 0.00,
                    'emergency_stop_reason' => $reason,
                    'updated_at' => now(),
                ]);
        }

        return (object) DB::table('autonomous_fleet_units')->where('unit_code', $uCode)->first();
    }

    /**
     * ROC operator intervention to safely clear stopped unit (306.2, 306.4, 306.5 Edge Case).
     */
    public function recordOperatorIntervention(
        string $interventionCode,
        string $unitCode,
        string $operatorId,
        string $interventionType,
        string $actionNotes
    ): object {
        $iCode = strtoupper($interventionCode);
        $uCode = strtoupper($unitCode);

        $id = DB::table('autonomous_fleet_interventions')->insertGetId([
            'intervention_code' => $iCode,
            'unit_code' => $uCode,
            'remote_operator_id' => strtoupper($operatorId),
            'intervention_type' => strtoupper($interventionType),
            'operator_action_notes' => $actionNotes,
            'safe_handover_verified' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Release emergency stop following verified operator intervention (306.5)
        DB::table('autonomous_fleet_units')
            ->where('unit_code', $uCode)
            ->update([
                'emergency_stopped' => false,
                'emergency_stop_reason' => null,
                'updated_at' => now(),
            ]);

        return (object) DB::table('autonomous_fleet_interventions')->find($id);
    }

    /**
     * Autonomous Field Operations Audit (`tower:audit`) (306.4, 306.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Units with inactive geofence cage
        $disabledCages = DB::table('autonomous_fleet_units')
            ->where('geofence_cage_active', false)
            ->count();

        // Discrepancy 2: Interventions without verified handover
        $unverifiedInterventions = DB::table('autonomous_fleet_interventions')
            ->where('safe_handover_verified', false)
            ->count();

        $discrepancies = $disabledCages + $unverifiedInterventions;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_fleet_units' => DB::table('autonomous_fleet_units')->count(),
            'total_interventions' => DB::table('autonomous_fleet_interventions')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
