<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class MallAuditBillingCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'mall:audit-billing';
    }

    public function corrupt(): string
    {
        DB::table('bank_ledger_accounts')->insert([
            'uuid' => (string) Str::uuid(),
            'code' => 'revenue:mall:corrupt',
            'name' => 'Pendapatan Mall Korup',
            'asset_code' => 'IDR',
            'kind' => 'revenue',
            'cached_balance' => '-50000',
            'allow_negative' => true,
            'is_frozen' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 'has negative balance';
    }
}
