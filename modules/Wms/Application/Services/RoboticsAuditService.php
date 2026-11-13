<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Wms\Models\RoboticsPlan;
use Modules\Wms\Models\RobotMission;

/**
 * Audit integritas robotika gudang: memastikan tidak ada dual active zone lock,
 * misi yatim, atau sertifikat ROI yang rusak.
 */
final class RoboticsAuditService
{
    /**
     * @return list<string> Daftar pelanggaran integritas (kosong = bersih)
     */
    public function findViolations(): array
    {
        $violations = [];

        $dualZoneLocks = DB::table('wms_robot_zone_locks')
            ->select('warehouse_id', 'zone_code', DB::raw('COUNT(*) as cnt'))
            ->where('lock_status', 'active')
            ->groupBy('warehouse_id', 'zone_code')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($dualZoneLocks as $row) {
            $violations[] = "Dual active zone lock pada gudang {$row->warehouse_id} zona {$row->zone_code} ({$row->cnt} lock aktif).";
        }

        $orphanMissions = RobotMission::query()
            ->leftJoin('wms_robot_units', 'wms_robot_missions.robot_unit_id', '=', 'wms_robot_units.id')
            ->whereNull('wms_robot_units.id')
            ->count();

        if ($orphanMissions > 0) {
            $violations[] = "Ditemukan {$orphanMissions} misi robot tanpa unit robot valid.";
        }

        $invalidRoiPlans = RoboticsPlan::query()
            ->where('capex_idr', '<=', 0)
            ->count();

        if ($invalidRoiPlans > 0) {
            $violations[] = "Ditemukan {$invalidRoiPlans} rencana automasi dengan capex <= 0.";
        }

        return $violations;
    }
}

