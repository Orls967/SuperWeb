<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SybMicrofinanceCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'syb:audit';
    }

    public function corrupt(): string
    {
        // Menyuntikkan akun bunga riba mikro pada buku besar syariah
        DB::table('bank_ledger_accounts')->insert([
            'uuid' => (string) Str::uuid(),
            'code' => 'syb:forbidden_micro_interest_charge:IDR',
            'asset_code' => 'IDR',
            'kind' => 'revenue',
            'name' => 'Forbidden Microfinance Conventional Interest Charge',
            'allow_negative' => false,
            'cached_balance' => '1250000',
            'is_frozen' => false,
            'created_at' => '2026-11-06 00:00:00',
            'updated_at' => '2026-11-06 00:00:00',
        ]);

        return 'syb.forbidden_interest_account';
    }
}
