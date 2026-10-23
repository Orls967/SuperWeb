<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class MfgAuditCostingCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'mfg:audit-costing';
    }

    public function corrupt(): string
    {
        DB::table('bank_ledger_accounts')->updateOrInsert(
            ['code' => 'inv:wip:IDR'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Persediaan Dalam Proses (WIP)',
                'asset_code' => 'IDR',
                'kind' => 'inventory',
                'allow_negative' => true,
                'cached_balance' => '99999999',
                'is_frozen' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return 'Audit costing menemukan selisih';
    }
}
