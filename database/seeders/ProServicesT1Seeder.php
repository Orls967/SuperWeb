<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class ProServicesT1Seeder extends Seeder
{
    public function run(): void
    {
        // 50 projects
        $now = now();
        $projects = [];
        for ($i = 1; $i <= 50; $i++) {
            $projects[] = [
                'id' => $i,
                'project_code' => sprintf('PRJ-T1-%04d', $i),
                'client_party_ref' => 100 + ($i % 10),
                'contract_reference' => sprintf('CTR-T1-%04d', $i),
                'billing_type' => ($i % 2 === 0) ? 'fixed_fee' : 'time_and_materials',
                'total_contract_idr' => 1000000000,
                'estimated_cost_idr' => 800000000,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('psv_projects')->insertOrIgnore($projects);

        // Members per project
        $members = [];
        $memberId = 1;
        for ($p = 1; $p <= 50; $p++) {
            for ($m = 1; $m <= 4; $m++) {
                $members[] = [
                    'id' => $memberId++,
                    'project_id' => $p,
                    'employee_ref' => ($p * 10) + $m,
                    'role_name' => 'Consultant Level '.$m,
                    'hourly_rate_idr' => 200000 * $m,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        DB::table('psv_project_members')->insertOrIgnore($members);

        // 20.000 timesheets chunked by 2000
        $chunks = 10;
        $perChunk = 2000;
        $tsId = 1;
        for ($c = 0; $c < $chunks; $c++) {
            $timesheets = [];
            for ($k = 0; $k < $perChunk; $k++) {
                $projId = (($tsId - 1) % 50) + 1;
                $mId = (($projId - 1) * 4) + ((($tsId - 1) % 4) + 1);
                $timesheets[] = [
                    'id' => $tsId,
                    'timesheet_code' => sprintf('TS-T1-%06d', $tsId),
                    'project_id' => $projId,
                    'project_member_id' => $mId,
                    'work_date' => '2026-11-05',
                    'hours_worked' => 8,
                    'description' => 'T1 regular consulting work',
                    'hourly_rate_idr' => 300000,
                    'status' => 'approved',
                    'approved_by_user_ref' => 1,
                    'approved_at' => $now,
                    'is_billed' => false,
                    'is_reversal' => false,
                    'reversed_timesheet_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $tsId++;
            }
            DB::table('psv_timesheets')->insertOrIgnore($timesheets);
        }
    }
}
