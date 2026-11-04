<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SybAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'syb:audit';
    }

    public function corrupt(): string
    {
        DB::table('bank_ledger_accounts')->insert([
            'uuid' => (string) Str::uuid(),
            'code' => 'syb:forbidden_interest_income:IDR',
            'asset_code' => 'IDR',
            'kind' => 'revenue',
            'name' => 'Forbidden Riba Interest Account',
            'allow_negative' => false,
            'cached_balance' => '100000',
            'is_frozen' => false,
            'created_at' => '2026-11-04 00:00:00',
            'updated_at' => '2026-11-04 00:00:00',
        ]);

        return 'syb.forbidden_interest_account';
    }
}
