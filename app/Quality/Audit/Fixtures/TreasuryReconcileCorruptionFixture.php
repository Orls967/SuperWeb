<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class TreasuryReconcileCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'treasury:reconcile';
    }

    public function corrupt(): string
    {
        DB::table('trs_currencies')->insertOrIgnore([
            'code' => 'IDR',
            'name' => 'Indonesian Rupiah',
            'symbol' => 'Rp',
            'minor_units' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $accId = (string) Str::uuid();
        DB::table('trs_bank_accounts')->insert([
            'id' => $accId,
            'account_number' => 'ACC-CORRUPT-'.Str::random(5),
            'bank_name' => 'Bank Mandiri',
            'currency' => 'IDR',
            'balance' => 10_000_000,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('trs_bank_statements')->insert([
            'id' => (string) Str::uuid(),
            'bank_account_id' => $accId,
            'transaction_date' => '2026-11-12',
            'reference_no' => 'REF-CORRUPT-01',
            'description' => 'Corrupted single-state statement',
            'amount' => 1_000_000,
            'is_reconciled' => true,
            'reconciliation_status' => 'unmatched',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 'Treasury reconcile FAILED';
    }
}
