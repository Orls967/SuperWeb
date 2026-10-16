<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class TreasuryAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'treasury:audit';
    }

    public function corrupt(): string
    {
        DB::table('trs_credit_facilities')->insert([
            'id' => (string) Str::uuid(),
            'facility_code' => 'FAC-CORRUPT-'.Str::random(5),
            'bank_name' => 'Bank Mandiri',
            'credit_limit_idr' => 10000000,
            'drawn_amount_idr' => 90000000, // drawn melebihi limit (melanggar invariant)
            'interest_rate_percent' => 8.5,
            'max_debt_equity_ratio' => 2.5,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 'Treasury audit FAILED';
    }
}
