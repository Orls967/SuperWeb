<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class SybOperationsCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'syb:audit';
    }

    public function corrupt(): string
    {
        // 1. Jika tabel syb_universal_wallet_transactions ada, buat transaksi sukses tanpa verifikasi halal
        if (Schema::hasTable('syb_universal_wallet_transactions')) {
            DB::table('syb_universal_wallet_transactions')->insert([
                'transaction_ref' => 'TX-CORRUPT-'.Str::random(8),
                'order_reference' => 'ORD-CORRUPT-'.Str::random(8),
                'customer_account_ref' => 'WALLET-CORRUPT-USER',
                'merchant_code' => 'MCH-ILLEGAL-NIGHTCLUB',
                'business_line_code' => 'BAR',
                'gross_amount_minor' => 500_000_00,
                'admin_fee_minor' => 5_000_00,
                'net_merchant_amount_minor' => 495_000_00,
                'is_halal_verified' => false, // Pelanggaran: status SUCCESS tetapi is_halal_verified = false
                'ledger_journal_ref' => 'JOURNAL-FAKE-001',
                'status' => 'SUCCESS',
                'rejection_reason' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return 'syb.unverified_halal_wallet_transaction';
        }

        // 2. Fallback: Menyuntikkan akun bunga riba operasional
        DB::table('bank_ledger_accounts')->insert([
            'uuid' => (string) Str::uuid(),
            'code' => 'syb:forbidden_ops_interest_charge:IDR',
            'asset_code' => 'IDR',
            'kind' => 'revenue',
            'name' => 'Forbidden Operations Conventional Interest Surcharge',
            'allow_negative' => false,
            'cached_balance' => '7500000',
            'is_frozen' => false,
            'created_at' => '2026-11-06 00:00:00',
            'updated_at' => '2026-11-06 00:00:00',
        ]);

        return 'syb.forbidden_interest_account';
    }
}
