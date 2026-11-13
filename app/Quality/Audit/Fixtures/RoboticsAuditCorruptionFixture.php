<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fixture korupsi dua sumber untuk command robotics:audit (D279-9, U-11).
 */
final class RoboticsAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'robotics:audit';
    }

    public function corrupt(): string
    {
        $whId = (string) Str::uuid();
        $now = now();

        DB::table('wms_warehouses')->insert([
            'id' => $whId,
            'code' => 'WH-ROB-CORRUPT',
            'name' => 'Gudang Robotika Korupsi',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $robotUnitId = DB::table('wms_robot_units')->insertGetId([
            'robot_code' => 'AMR-UNIT-CORRUPT',
            'warehouse_id' => $whId,
            'kind' => 'amr',
            'battery_pct' => 90,
            'status' => 'active',
            'current_zone_code' => 'Z-CORRUPT',
            'max_speed_mm_s' => 1800,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $zoneId = DB::table('wms_traffic_zones')->insertGetId([
            'warehouse_id' => $whId,
            'zone_code' => 'Z-CORRUPT',
            'zone_kind' => 'corridor',
            'capacity_k' => 1,
            'staff_blocked' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('wms_robot_missions')->insert([
            'mission_code' => 'MIS-CORRUPT-01',
            'warehouse_id' => $whId,
            'robot_unit_id' => $robotUnitId,
            'traffic_zone_id' => $zoneId,
            'zone_code' => 'Z-CORRUPT',
            'status' => 'moving',
            'fallback_manual' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return 'MOVING_WITHOUT_RESERVATION:MIS-CORRUPT-01';
    }
}
