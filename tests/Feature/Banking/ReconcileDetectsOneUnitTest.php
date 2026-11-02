<?php

declare(strict_types=1);

namespace Tests\Feature\Banking;

use App\Models\User;
use Brick\Math\BigDecimal;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerEntry;
use Tests\TestCase;

class ReconcileDetectsOneUnitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LedgerAccountsSeeder::class);
        $this->seed(BankingSeeder::class);
    }

    public function test_reconcile_detects_one_rupiah_discrepancy_on_idr_ledger(): void
    {
        $user = User::factory()->create();
        $userWallet = $user->walletAccount('IDR');
        $clearing = LedgerAccount::where('code', 'clearing:external:IDR')->firstOrFail();

        $service = app(LedgerService::class);

        // Posting transaksi IDR normal 1.000.000
        $tx = $service->post(new PostingDTO(
            type: TransactionType::TOPUP->value,
            description: 'Topup IDR normal',
            idempotencyKey: 'reconcile:idr:normal:1',
            entries: [
                PostingEntryDTO::forAccount($clearing->id, 'IDR', -1_000_000),
                PostingEntryDTO::forAccount($userWallet->id, 'IDR', 1_000_000),
            ],
            referenceType: 'test_reconcile',
            referenceId: 1,
        ));

        $this->artisan('bank:reconcile')->assertSuccessful();

        // Sabotase 1 entri sebesar 1 IDR (mengubah -1000000 menjadi -999999)
        $clearingEntry = LedgerEntry::where('transaction_id', $tx->id)
            ->where('account_id', $clearing->id)
            ->firstOrFail();

        DB::statement('DROP TRIGGER IF EXISTS prevent_bank_ledger_entries_update');
        DB::table('bank_ledger_entries')
            ->where('id', $clearingEntry->id)
            ->update(['amount' => '-999999']);

        // bank:reconcile wajib gagal dan melaporkan ketidakcocokan pada kode akun clearing
        $this->artisan('bank:reconcile')
            ->expectsOutputToContain('Ketidakcocokan saldo pada akun: clearing:external:IDR')
            ->assertFailed();
    }

    public function test_reconcile_detects_one_atto_unit_1e_minus_18_discrepancy_on_crypto_ledger(): void
    {
        $user = User::factory()->create();
        $userBtc = $user->walletAccount('BTC');
        $clearing = LedgerAccount::where('code', 'clearing:external:BTC')->firstOrFail();

        $service = app(LedgerService::class);

        // Posting transaksi BTC senilai 100.000000000000000000
        $amount = '100.000000000000000000';
        $tx = $service->post(new PostingDTO(
            type: TransactionType::EXCHANGE->value,
            description: 'Deposit BTC 100 unit',
            idempotencyKey: 'reconcile:btc:atto:1',
            entries: [
                PostingEntryDTO::forAccount($clearing->id, 'BTC', BigDecimal::of($amount)->negated()),
                PostingEntryDTO::forAccount($userBtc->id, 'BTC', BigDecimal::of($amount)),
            ],
            referenceType: 'test_reconcile_crypto',
            referenceId: 1,
        ));

        $this->artisan('bank:reconcile')->assertSuccessful();

        // Sabotase 1 entri sebesar 10^-18 (-100.000000000000000001)
        $clearingEntry = LedgerEntry::where('transaction_id', $tx->id)
            ->where('account_id', $clearing->id)
            ->firstOrFail();

        $sabotagedAmount = BigDecimal::of($clearingEntry->amount)
            ->minus('0.000000000000000001')
            ->__toString();

        DB::statement('DROP TRIGGER IF EXISTS prevent_bank_ledger_entries_update');
        DB::table('bank_ledger_entries')
            ->where('id', $clearingEntry->id)
            ->update(['amount' => $sabotagedAmount]);

        // Verifikasi bank:reconcile penuh gagal mendeteksi saldo dan selisih -0.000000000000000001
        $this->artisan('bank:reconcile')
            ->expectsOutputToContain('Ketidakcocokan saldo pada akun: clearing:external:BTC')
            ->expectsOutputToContain('Total global aset BTC tidak seimbang: -0.000000000000000001')
            ->assertFailed();

        // Verifikasi bank:reconcile --incremental juga gagal mendeteksi
        $this->artisan('bank:reconcile', ['--incremental' => true])
            ->expectsOutputToContain('Ketidakcocokan saldo pada akun: clearing:external:BTC')
            ->expectsOutputToContain('Total global aset BTC tidak seimbang: -0.000000000000000001')
            ->assertFailed();
    }

    public function test_incremental_reconcile_detects_stale_or_corrupted_checkpoint(): void
    {
        $user = User::factory()->create();
        $userWallet = $user->walletAccount('IDR');
        $clearing = LedgerAccount::where('code', 'clearing:external:IDR')->firstOrFail();

        $service = app(LedgerService::class);

        // Transaksi 1
        $service->post(new PostingDTO(
            type: TransactionType::TOPUP->value,
            description: 'Topup 1 checkpoint',
            idempotencyKey: 'reconcile:checkpoint:tx:1',
            entries: [
                PostingEntryDTO::forAccount($clearing->id, 'IDR', -200_000),
                PostingEntryDTO::forAccount($userWallet->id, 'IDR', 200_000),
            ],
            referenceType: 'test_checkpoint',
            referenceId: 1,
        ));

        // Buat Checkpoint #1
        $this->artisan('bank:reconcile', ['--incremental' => true])->assertSuccessful();

        $checkpoint = DB::table('bank_reconcile_checkpoints')->latest('id')->first();
        $this->assertNotNull($checkpoint);

        // Transaksi 2
        $service->post(new PostingDTO(
            type: TransactionType::TOPUP->value,
            description: 'Topup 2 checkpoint',
            idempotencyKey: 'reconcile:checkpoint:tx:2',
            entries: [
                PostingEntryDTO::forAccount($clearing->id, 'IDR', -300_000),
                PostingEntryDTO::forAccount($userWallet->id, 'IDR', 300_000),
            ],
            referenceType: 'test_checkpoint',
            referenceId: 2,
        ));

        // Sabotase account_balances pada checkpoint #1 (korupsi 1 unit pada saldo akun user)
        $balances = json_decode((string) $checkpoint->account_balances, true);
        $balances[$userWallet->id] = (string) (BigDecimal::of($balances[$userWallet->id] ?? '0')->plus('1'));

        DB::table('bank_reconcile_checkpoints')
            ->where('id', $checkpoint->id)
            ->update(['account_balances' => json_encode($balances)]);

        // bank:reconcile --incremental wajib gagal mendeteksi saldo tidak cocok
        $this->artisan('bank:reconcile', ['--incremental' => true])
            ->expectsOutputToContain("Ketidakcocokan saldo pada akun: {$userWallet->code}")
            ->assertFailed();
    }
}
