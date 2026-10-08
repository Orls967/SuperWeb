<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * WarehouseRoboticsAutomationService (Fase 279)
 *
 * Implements:
 *  - 279.1 Distribution Center automation planning & picking wave capacity feasibility
 *  - 279.2 Robot fleet traffic management with mutual zone reservations preventing collisions
 *  - 279.4 Fallback to manual execution when AMR robots fail, avoiding wave cancellation
 *  - 279.5 Edge case: Robot failure mid-wave triggers automated manual fallback + wave replanning with SLA preserved
 *  - 279.6 Safety interlock: human presence in zone immediately halts or enforces strict speed limits
 */
class WarehouseRoboticsAutomationService
{
    /**
     * Register warehouse zone reservation preventing spatial collision (279.2 & 279.4).
     */
    public function reserveZone(
        string $zoneId,
        string $robotId,
        bool $humanPresent = false
    ): object {
        $zone = strtoupper($zoneId);
        $robot = strtoupper($robotId);

        // Safety interlock check (279.6): Human present restricts automated entry / halts
        if ($humanPresent) {
            throw new InvalidArgumentException("Safety interlock alert: Human worker present in zone '{$zoneId}'. Automated vehicle entry restricted (279.6).");
        }

        // Check mutual reservation conflict (279.2 & 279.4)
        $existing = DB::table('warehouse_zone_reservations')
            ->where('zone_id', $zone)
            ->where('is_reserved', true)
            ->first();

        if ($existing && $existing->active_robot_id !== $robot) {
            throw new InvalidArgumentException("Collision prevention error: Zone '{$zoneId}' already reserved by robot '{$existing->active_robot_id}' (279.4).");
        }

        DB::table('warehouse_zone_reservations')->updateOrInsert(
            ['zone_id' => $zone],
            [
                'active_robot_id' => $robot,
                'is_reserved' => true,
                'safety_interlock_active' => true,
                'human_present_in_zone' => false,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return (object) DB::table('warehouse_zone_reservations')->where('zone_id', $zone)->first();
    }

    /**
     * Release zone reservation upon robot exit (279.2).
     */
    public function releaseZone(string $zoneId): void
    {
        $zone = strtoupper($zoneId);
        DB::table('warehouse_zone_reservations')
            ->where('zone_id', $zone)
            ->update([
                'active_robot_id' => null,
                'is_reserved' => false,
                'updated_at' => now(),
            ]);
    }

    /**
     * Register AMR robot in fleet (279.2).
     */
    public function registerRobot(string $robotCode, string $modelType): object
    {
        $code = strtoupper($robotCode);

        $id = DB::table('warehouse_robot_fleet')->insertGetId([
            'robot_code' => $code,
            'model_type' => strtoupper($modelType),
            'battery_level_pct' => 100,
            'status' => 'IDLE',
            'manual_fallback_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('warehouse_robot_fleet')->find($id);
    }

    /**
     * Plan picking wave enforcing capacity feasibility (279.3 & 279.4).
     */
    public function planPickingWave(
        string $waveCode,
        string $assignedRobotId,
        int $totalItemsCount,
        float $pickCapacityLimit
    ): object {
        $code = strtoupper($waveCode);

        // Capacity feasibility constraint (279.4): Items cannot exceed picker capacity limit
        if ($totalItemsCount > $pickCapacityLimit) {
            throw new InvalidArgumentException("Wave capacity feasibility error: Total items ({$totalItemsCount}) exceeds pick capacity ({$pickCapacityLimit}) (279.4).");
        }

        $id = DB::table('warehouse_picking_waves')->insertGetId([
            'wave_code' => $code,
            'assigned_robot_id' => strtoupper($assignedRobotId),
            'total_items_count' => $totalItemsCount,
            'pick_capacity_limit' => $pickCapacityLimit,
            'is_re为其plann_required' => false,
            'status' => 'IN_PROGRESS',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('warehouse_picking_waves')->find($id);
    }

    /**
     * Handle robot mid-wave failure with automatic manual fallback & wave replan (279.4 & 279.5 Edge Case).
     */
    public function handleRobotFailure(string $robotCode, string $waveCode): object
    {
        $rCode = strtoupper($robotCode);
        $wCode = strtoupper($waveCode);

        // Mark robot as failed and activate manual fallback (279.4 & 279.5)
        DB::table('warehouse_robot_fleet')
            ->where('robot_code', $rCode)
            ->update([
                'status' => 'FAILED_DOWN',
                'manual_fallback_active' => true,
                'updated_at' => now(),
            ]);

        // Re-plan wave with manual fulfillment preserving SLA (279.5)
        DB::table('warehouse_picking_waves')
            ->where('wave_code', $wCode)
            ->update([
                'status' => 'FALLBACK_MANUAL',
                'is_re为其plann_required' => true,
                'updated_at' => now(),
            ]);

        return (object) DB::table('warehouse_picking_waves')->where('wave_code', $wCode)->first();
    }

    /**
     * Warehouse Automation Platform Audit (`wms:audit`) (279.4, 279.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Failed robots without manual fallback activated
        $unfallbackRobots = DB::table('warehouse_robot_fleet')
            ->where('status', 'FAILED_DOWN')
            ->where('manual_fallback_active', false)
            ->count();

        // Discrepancy 2: Zones reserved without active robot ID
        $staleReservations = DB::table('warehouse_zone_reservations')
            ->where('is_reserved', true)
            ->whereNull('active_robot_id')
            ->count();

        // Discrepancy 3: Waves exceeding pick capacity limit
        $infeasibleWaves = DB::table('warehouse_picking_waves')
            ->whereRaw('total_items_count > pick_capacity_limit')
            ->count();

        $discrepancies = $unfallbackRobots + $staleReservations + $infeasibleWaves;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_robots' => DB::table('warehouse_robot_fleet')->count(),
            'total_zones' => DB::table('warehouse_zone_reservations')->count(),
            'total_waves' => DB::table('warehouse_picking_waves')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
