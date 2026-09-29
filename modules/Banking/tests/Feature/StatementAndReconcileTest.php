<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Banking\Application\Actions\SetPinAction;
use Modules\Banking\Application\Actions\TopUpAction;
use Modules\Banking\Application\Actions\TransferAction;
use Modules\Banking\Application\Queries\StatementQuery;
use Modules\Banking\Domain\Models\LedgerAccount;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Modules\Banking\database\seeders\BankingSeeder']);

    $this->user = User::create([
        'name' => 'Statement User',
        'email' => 'statement.user@example.com',
        'password' => Hash::make('password'),
        'role' => 'customer',
    ]);

    $this->other = User::create([
        'name' => 'Other User',
        'email' => 'other.user@example.com',
        'password' => Hash::make('password'),
        'role' => 'customer',
    ]);

    app(SetPinAction::class)->execute($this->user, '123456');
    app(SetPinAction::class)->execute($this->other, '123456');

    app(TopUpAction::class)->execute($this->user, '2000000', 'topup_statement_1');
    app(TransferAction::class)->execute($this->user, $this->other, '500000', '123456', 'Makan siang');
});

test('StatementQuery memfilter mutasi berdasarkan tipe transaksi', function () {
    $account = $this->user->walletAccount('IDR');
    $query = app(StatementQuery::class);

    $all = $query->paginate($account);
    expect($all->total())->toBe(2);

    $topupsOnly = $query->paginate($account, type: 'topup');
    expect($topupsOnly->total())->toBe(1);

    $transfersOnly = $query->paginate($account, type: 'transfer');
    expect($transfersOnly->total())->toBe(1);
});

test('StatementQuery mengekspor data ke file CSV dengan header lengkap', function () {
    $account = $this->user->walletAccount('IDR');
    $query = app(StatementQuery::class);

    $response = $query->exportCsv($account);

    expect($response->headers->get('content-type'))->toContain('text/csv');

    ob_start();
    $response->sendContent();
    $content = ob_get_clean();

    expect($content)->toContain('ID Entri')
        ->and($content)->toContain('Tanggal & Waktu')
        ->and($content)->toContain('Tipe Transaksi')
        ->and($content)->toContain('Jumlah')
        ->and($content)->toContain('Saldo Setelah');
});

test('command bank:reconcile berjalan sukses dan mengonfirmasi keseimbangan ledger', function () {
    $this->artisan('bank:reconcile')
        ->assertSuccessful()
        ->expectsOutputToContain('Semua akun seimbang dan total global per aset = 0');
});

test('command bank:reconcile mendeteksi inkonsistensi saldo jika terjadi manipulasi cached_balance', function () {
    // Sengaja ubah cached_balance tanpa entry untuk memicu deteksi selisih
    $account = LedgerAccount::where('code', 'clearing:external:IDR')->first();
    $account->cached_balance = '999999999';
    $account->save();

    $this->artisan('bank:reconcile')
        ->assertFailed()
        ->expectsOutputToContain('DITEMUKAN SELISIH PADA LEDGER');
});
