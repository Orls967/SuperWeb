<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EpcAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'epc:audit';
    }

    public function corrupt(): string
    {
        DB::table('epc_projects')->insert([
            'id' => (string) Str::uuid(),
            'project_code' => 'PRJ-CORRUPT-'.Str::random(5),
            'project_name' => 'Proyek Konstruksi Korupsi Progres',
            'project_type' => 'construction',
            'client_entity_id' => (string) Str::uuid(),
            'total_rab_budget_idr' => 100000000,
            'target_physical_progress_pct' => 100.0,
            'actual_physical_progress_pct' => 180.50, // melebihi 100%
            'accumulated_cip_cost_idr' => 10000000,
            'capitalized_asset_value_idr' => 0,
            'start_date' => now()->toDateString(),
            'target_completion_date' => now()->addMonths(6)->toDateString(),
            'status' => 'in_progress',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 'actual progress exceeds 100%';
    }
}
