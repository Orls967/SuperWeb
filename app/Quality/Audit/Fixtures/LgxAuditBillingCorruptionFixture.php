<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class LgxAuditBillingCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'lgx:audit-billing';
    }

    public function corrupt(): string
    {
        // Korupsikan saldo ledger freight revenue logistik
        DB::table('bank_ledger_accounts')->updateOrInsert(
            ['code' => 'lgx:freight_revenue', 'asset_code' => 'IDR'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Pendapatan Freight Logistik',
                'asset_code' => 'IDR',
                'kind' => 'revenue',
                'allow_negative' => true,
                'cached_balance' => '888888888', // Selisih dengan total tagihan freight
                'is_frozen' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return 'SELISIH';
    }
}
