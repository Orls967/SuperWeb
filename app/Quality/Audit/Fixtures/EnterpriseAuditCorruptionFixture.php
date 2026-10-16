<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EnterpriseAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'enterprise:audit';
    }

    public function corrupt(): string
    {
        DB::table('ef_budgets')->insert([
            'budget_code' => 'BDG-CORRUPT-'.Str::random(5),
            'fiscal_year' => '2026',
            'cost_center_code' => 'CC-999',
            'account_code' => 'ACC-999',
            'allocated_amount_idr' => 1000000,
            'encumbered_amount_idr' => 2000000, // melebihi alokasi pada HARD_STOP
            'spent_amount_idr' => 500000,
            'control_type' => 'HARD_STOP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 'Enterprise finance audit FAILED';
    }
}
