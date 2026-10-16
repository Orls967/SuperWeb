<?php

declare(strict_types=1);

namespace Tests\Feature\Banking;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Models\LedgerAccount;
use Tests\TestCase;

class ReconcilePortableAndCorruptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconcile_handles_10_pow_15_minor_units_without_precision_loss(): void
    {
        $largeAmount = '1000000000000000'; // 10^15 minor units
        $sourceAccount = LedgerAccount::create([
            'code' => 'test:large_val:source:IDR',
            'name' => 'Test Large Source',
            'asset_code' => 'IDR',
            'kind' => AccountKind::CASH,
            'allow_negative' => true,
            'cached_balance' => "-{$largeAmount}",
        ]);

        $destAccount = LedgerAccount::create([
            'code' => 'test:large_val:dest:IDR',
            'name' => 'Test Large Dest',
            'asset_code' => 'IDR',
            'kind' => AccountKind::REVENUE,
            'allow_negative' => true,
            'cached_balance' => $largeAmount,
        ]);

        $txId = DB::table('bank_ledger_transactions')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'type' => 'transfer',
            'idempotency_key' => 'reconcile-test-large-10-15',
            'payload_hash' => hash('sha256', 'reconcile-test-large-10-15'),
            'description' => 'Test Large Amount 10^15',
            'posted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('bank_ledger_entries')->insert([
            [
                'transaction_id' => $txId,
                'account_id' => $sourceAccount->id,
                'asset_code' => 'IDR',
                'amount' => "-{$largeAmount}",
                'balance_after' => "-{$largeAmount}",
                'created_at' => now(),
            ],
            [
                'transaction_id' => $txId,
                'account_id' => $destAccount->id,
                'asset_code' => 'IDR',
                'amount' => $largeAmount,
                'balance_after' => $largeAmount,
                'created_at' => now(),
            ],
        ]);

        $this->artisan('bank:reconcile')
            ->assertSuccessful()
            ->expectsOutputToContain('Semua akun seimbang dan total global per aset = 0');
    }

    public function test_reconcile_detects_corrupted_entry_and_reports_account_name(): void
    {
        $accountA = LedgerAccount::create([
            'code' => 'test:corrupt:source:IDR',
            'name' => 'Akun Uji Korupsi Sumber',
            'asset_code' => 'IDR',
            'kind' => AccountKind::CASH,
            'allow_negative' => true,
            'cached_balance' => '-50000',
        ]);

        $accountB = LedgerAccount::create([
            'code' => 'test:corrupt:dest:IDR',
            'name' => 'Akun Uji Korupsi Tujuan',
            'asset_code' => 'IDR',
            'kind' => AccountKind::REVENUE,
            'allow_negative' => true,
            'cached_balance' => '50000',
        ]);

        $txId = DB::table('bank_ledger_transactions')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'type' => 'transfer',
            'idempotency_key' => 'reconcile-corrupt-tx',
            'payload_hash' => hash('sha256', 'reconcile-corrupt-tx'),
            'description' => 'Test Normal Tx',
            'posted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $entryId = DB::table('bank_ledger_entries')->insertGetId([
            'transaction_id' => $txId,
            'account_id' => $accountA->id,
            'asset_code' => 'IDR',
            'amount' => '-50000',
            'balance_after' => '-50000',
            'created_at' => now(),
        ]);

        DB::table('bank_ledger_entries')->insert([
            'transaction_id' => $txId,
            'account_id' => $accountB->id,
            'asset_code' => 'IDR',
            'amount' => '50000',
            'balance_after' => '50000',
            'created_at' => now(),
        ]);

        // Pastikan awalnya seimbang
        $this->artisan('bank:reconcile')->assertSuccessful();

        // Korupsi 1 entri: ubah -50000 menjadi -49999
        DB::table('bank_ledger_entries')->where('id', $entryId)->update(['amount' => '-49999']);

        // Jalankan rekonsiliasi: harus gagal dan menyebutkan nama/kode akun yang korup
        $this->artisan('bank:reconcile')
            ->assertFailed()
            ->expectsOutputToContain('Ketidakcocokan saldo pada akun: test:corrupt:source:IDR');
    }

    public function test_incremental_reconciliation_saves_and_resumes_from_checkpoints(): void
    {
        $accountA = LedgerAccount::create([
            'code' => 'test:inc:a:IDR',
            'name' => 'Akun Inkremental A',
            'asset_code' => 'IDR',
            'kind' => AccountKind::CASH,
            'allow_negative' => true,
            'cached_balance' => '-10000',
        ]);

        $accountB = LedgerAccount::create([
            'code' => 'test:inc:b:IDR',
            'name' => 'Akun Inkremental B',
            'asset_code' => 'IDR',
            'kind' => AccountKind::REVENUE,
            'allow_negative' => true,
            'cached_balance' => '10000',
        ]);

        $tx1 = DB::table('bank_ledger_transactions')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'type' => 'transfer',
            'idempotency_key' => 'reconcile-inc-tx-1',
            'payload_hash' => hash('sha256', 'reconcile-inc-tx-1'),
            'description' => 'Tx 1',
            'posted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('bank_ledger_entries')->insert([
            [
                'transaction_id' => $tx1,
                'account_id' => $accountA->id,
                'asset_code' => 'IDR',
                'amount' => '-10000',
                'balance_after' => '-10000',
                'created_at' => now(),
            ],
            [
                'transaction_id' => $tx1,
                'account_id' => $accountB->id,
                'asset_code' => 'IDR',
                'amount' => '10000',
                'balance_after' => '10000',
                'created_at' => now(),
            ],
        ]);

        // Checkpoint awal
        $this->artisan('bank:reconcile', ['--incremental' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Checkpoint rekonsiliasi tersimpan');

        $this->assertDatabaseCount('bank_reconcile_checkpoints', 1);

        // Tambahkan transaksi kedua
        $accountA->cached_balance = '-25000';
        $accountA->save();
        $accountB->cached_balance = '25000';
        $accountB->save();

        $tx2 = DB::table('bank_ledger_transactions')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'type' => 'transfer',
            'idempotency_key' => 'reconcile-inc-tx-2',
            'payload_hash' => hash('sha256', 'reconcile-inc-tx-2'),
            'description' => 'Tx 2',
            'posted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('bank_ledger_entries')->insert([
            [
                'transaction_id' => $tx2,
                'account_id' => $accountA->id,
                'asset_code' => 'IDR',
                'amount' => '-15000',
                'balance_after' => '-25000',
                'created_at' => now(),
            ],
            [
                'transaction_id' => $tx2,
                'account_id' => $accountB->id,
                'asset_code' => 'IDR',
                'amount' => '15000',
                'balance_after' => '25000',
                'created_at' => now(),
            ],
        ]);

        // Lanjutkan rekonsiliasi inkremental
        $this->artisan('bank:reconcile', ['--incremental' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Melanjutkan dari checkpoint #1')
            ->expectsOutputToContain('Checkpoint rekonsiliasi tersimpan');

        $this->assertDatabaseCount('bank_reconcile_checkpoints', 2);
    }
}
