<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EcosystemAudit12LinesCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'ecosystem:audit-12-lines';
    }

    public function corrupt(): string
    {
        DB::table('bank_ledger_accounts')->insert([
            'id' => DB::table('bank_ledger_accounts')->max('id') + 1,
            'uuid' => (string) Str::uuid(),
            'code' => 'asset:ecosystem:corrupt:IDR',
            'name' => 'Akun Korupsi Ekosistem 12 Lini',
            'asset_code' => 'IDR',
            'kind' => 'asset',
            'allow_negative' => false,
            'cached_balance' => '-999999', // Saldo negatif pada akun aset yang dilarang negatif
            'is_frozen' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 'Ledger Invariant Breach';
    }
}
