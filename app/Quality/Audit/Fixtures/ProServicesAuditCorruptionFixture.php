<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\ProServices\Domain\Models\ProServicesProject;

final class ProServicesAuditCorruptionFixture extends Seeder
{
    public function run(): void
    {
        // Masukkan manipulasi data: timesheet ditandai billed tanpa approved
        $project = ProServicesProject::first();
        if (! $project) {
            $projectId = DB::table('psv_projects')->insertGetId([
                'project_code' => 'PRJ-CORRUPT-001',
                'client_party_id' => 999,
                'contract_reference' => 'CTR-CORRUPT',
                'billing_type' => 'fixed_fee',
                'total_contract_idr' => 1000000,
                'estimated_cost_idr' => 800000,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $project = ProServicesProject::find($projectId);
        }

        $memberId = DB::table('psv_project_members')->insertGetId([
            'project_id' => $project->id,
            'employee_id' => 999,
            'role_name' => 'Ghost Consultant',
            'hourly_rate_idr' => 100000,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('psv_timesheets')->insert([
            'timesheet_code' => 'TS-CORRUPT-001',
            'project_id' => $project->id,
            'project_member_id' => $memberId,
            'work_date' => '2026-11-05',
            'hours_worked' => 10,
            'description' => 'Unapproved but billed corruption fixture',
            'hourly_rate_idr' => 100000,
            'status' => 'submitted', // Belum disetujui!
            'is_billed' => true,      // Sengaja ditagihkan!
            'is_reversal' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
