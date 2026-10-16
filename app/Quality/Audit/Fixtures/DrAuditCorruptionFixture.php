<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DrAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'dr:audit';
    }

    public function corrupt(): string
    {
        DB::table('res_dr_drill_results')->insert([
            'drill_code' => 'DRILL-CORRUPT-'.Str::random(5),
            'drill_type' => 'FAILOVER',
            'lines_tested' => json_encode(['HOS', 'FIN']),
            'status' => 'FAILED',
            'actual_rto_minutes' => 999,
            'data_loss_detected' => true,
            'ledger_discrepancy' => 5000000,
            'audit_clean' => false,
            'findings' => 'Intentional disaster recovery failure fixture',
            'drilled_at' => now(),
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 'dr:audit FAILED';
    }
}
