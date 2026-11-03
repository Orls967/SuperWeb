<?php

declare(strict_types=1);

namespace Tests\Feature\Banking;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Models\LedgerAccount;
use Tests\TestCase;

class ReconcilePortableAndCorruptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_reconcile_handles_10_pow_15_minor_units_without_precision_loss(): void
    {
        $largeAmount = '1000000000000000'; // 10^15 minor units
        $sourceAccount = LedgerAccount::where('code', 'clearing:external:IDR')->firstOrFail();
        $sourceAccount->update(['cached_balance' => "-{$largeAmount}"]);

        $destAccount = LedgerAccount::where('code', 'resto:catering_revenue:IDR')->firstOrFail();
        $destAccount->update(['cached_balance' => $largeAmount]);

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
        $accountA = LedgerAccount::where('code', 'clearing:external:IDR')->firstOrFail();
        $accountA->update(['cached_balance' => '-50000']);

        $accountB = LedgerAccount::where('code', 'resto:catering_revenue:IDR')->firstOrFail();
        $accountB->update(['cached_balance' => '50000']);

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
        DB::statement('DROP TRIGGER IF EXISTS prevent_bank_ledger_entries_update');
        DB::table('bank_ledger_entries')->where('id', $entryId)->update(['amount' => '-49999']);

        // Jalankan rekonsiliasi: harus gagal dan menyebutkan nama/kode akun yang korup
        $this->artisan('bank:reconcile')
            ->assertFailed()
            ->expectsOutputToContain('Ketidakcocokan saldo pada akun: clearing:external:IDR');
    }

    public function test_incremental_reconciliation_saves_and_resumes_from_checkpoints(): void
    {
        $accountA = LedgerAccount::where('code', 'clearing:external:IDR')->firstOrFail();
        $accountA->update(['cached_balance' => '-10000']);

        $accountB = LedgerAccount::where('code', 'resto:catering_revenue:IDR')->firstOrFail();
        $accountB->update(['cached_balance' => '10000']);

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
