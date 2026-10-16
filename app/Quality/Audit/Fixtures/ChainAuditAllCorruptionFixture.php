<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ChainAuditAllCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'chain:audit-all';
    }

    public function corrupt(): string
    {
        // Korupsikan saldo ledger agar bank:reconcile gagal di dalam chain:audit-all
        DB::table('bank_ledger_accounts')->updateOrInsert(
            ['code' => 'corrupted:test:account:IDR'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Akun Korupsi Chain Audit',
                'asset_code' => 'IDR',
                'kind' => 'revenue',
                'allow_negative' => true,
                'cached_balance' => '999999999',
                'is_frozen' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return 'FAIL: bank:reconcile';
    }
}
