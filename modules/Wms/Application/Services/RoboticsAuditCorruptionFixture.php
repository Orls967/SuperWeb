<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ClockInterface;

/**
 * Fixture pengujian anti-tautologis untuk menguji bahwa wms:audit gagal
 * ketika integritas kunci zona robotika dirusak.
 */
final readonly class RoboticsAuditCorruptionFixture
{
    public function __construct(
        private ClockInterface $clock,
    ) {}

    public function injectDualActiveZoneLocks(
        string $warehouseId,
        string $zoneCode,
        int $firstRobotId,
        int $secondRobotId,
    ): void {
        $now = $this->clock->now();

        DB::table('wms_robot_zone_locks')->insert([
            [
                'warehouse_id' => $warehouseId,
                'zone_code' => $zoneCode,
                'robot_unit_id' => $firstRobotId,
                'human_present' => false,
                'interlock_active' => false,
                'speed_limit_mm_s' => 1800,
                'lock_status' => 'active',
                'locked_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'warehouse_id' => $warehouseId,
                'zone_code' => $zoneCode,
                'robot_unit_id' => $secondRobotId,
                'human_present' => false,
                'interlock_active' => false,
                'speed_limit_mm_s' => 1800,
                'lock_status' => 'active',
                'locked_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}

