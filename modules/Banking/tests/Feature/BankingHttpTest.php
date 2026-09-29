<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Banking\Application\Actions\SetPinAction;
use Modules\Banking\Application\Actions\TopUpAction;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerTransaction;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Modules\Banking\database\seeders\BankingSeeder']);

    $this->admin = User::create([
        'name' => 'Admin Bank',
        'email' => 'admin.bank@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
    ]);

    $this->customer = User::create([
        'name' => 'Customer Bank',
        'email' => 'customer.bank@example.com',
        'password' => Hash::make('password'),
        'role' => 'customer',
    ]);

    $this->customer2 = User::create([
        'name' => 'Customer Bank 2',
        'email' => 'customer2.bank@example.com',
        'password' => Hash::make('password'),
        'role' => 'customer',
    ]);

    app(SetPinAction::class)->execute($this->customer, '123456');
    app(SetPinAction::class)->execute($this->customer2, '654321');
});

test('user dapat mengakses halaman wallet dan melihat saldo', function () {
    app(TopUpAction::class)->execute($this->customer, '1000000', 'topup_http_1');

    $response = $this->actingAs($this->customer)->get(route('wallet.index'));

    $response->assertOk()
        ->assertSee('Dompet Utama')
        ->assertSee('Rp 1.000.000');
});

test('user dapat mengatur PIN baru via HTTP POST', function () {
    $response = $this->actingAs($this->customer)->post(route('wallet.pin.store'), [
        'pin' => '654321',
        'pin_confirmation' => '654321',
    ]);

    $response->assertRedirect()
        ->assertSessionHas('status');
});

test('user dapat melakukan top up saldo via HTTP POST', function () {
    $response = $this->actingAs($this->customer)->post(route('wallet.topup.store'), [
        'amount' => '500000',
    ]);

    $response->assertRedirect(route('wallet.index'))
        ->assertSessionHas('status');

    expect($this->customer->walletBalance('IDR')->amount->toInt())->toBe(500000);
});

test('endpoint lookup transfer mengembalikan nama tersamar dan info penerima', function () {
    $response = $this->actingAs($this->customer)->postJson(route('wallet.transfer.lookup'), [
        'query' => 'customer2.bank@example.com',
    ]);

    $response->assertOk()
        ->assertJson([
            'found' => true,
            'email' => 'customer2.bank@example.com',
        ]);

    $data = $response->json();
    expect($data['name'])->toContain('*');
});

test('user dapat melakukan transfer dana antar akun via HTTP POST', function () {
    app(TopUpAction::class)->execute($this->customer, '2000000', 'topup_for_transfer');

    $response = $this->actingAs($this->customer)->post(route('wallet.transfer.store'), [
        'recipient_query' => $this->customer2->email,
        'amount' => '500000',
        'pin' => '123456',
        'note' => 'Transfer via web',
    ]);

    $response->assertRedirect(route('wallet.index'))
        ->assertSessionHas('status');

    expect($this->customer2->walletBalance('IDR')->amount->toInt())->toBe(500000);
});

test('user dapat melihat halaman mutasi dan mengekspor CSV', function () {
    app(TopUpAction::class)->execute($this->customer, '1000000', 'topup_for_mutasi');

    $response = $this->actingAs($this->customer)->get(route('wallet.mutasi'));
    $response->assertOk()
        ->assertSee('Daftar Transaksi Rekening');

    $csvResponse = $this->actingAs($this->customer)->get(route('wallet.mutasi.export'));
    $csvResponse->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

test('customer non-admin tidak dapat mengakses halaman admin ledger', function () {
    $response = $this->actingAs($this->customer)->get(route('admin.ledger.index'));
    $response->assertForbidden();
});

test('admin dapat mengakses ledger, melihat transaksi, freeze akun dan penyesuaian manual', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.ledger.index'));
    $response->assertOk()
        ->assertSee('Daftar Akun Sistem')
        ->assertSee('clearing:external:IDR');

    // Freeze account
    $acc = LedgerAccount::where('code', 'clearing:external:IDR')->first();
    $freezeResp = $this->actingAs($this->admin)->post(route('admin.ledger.freeze', $acc));
    $freezeResp->assertRedirect();
    expect($acc->fresh()->is_frozen)->toBeTrue();

    // Unfreeze
    $this->actingAs($this->admin)->post(route('admin.ledger.freeze', $acc));
    expect($acc->fresh()->is_frozen)->toBeFalse();

    // Manual adjustment
    $adjustResp = $this->actingAs($this->admin)->post(route('admin.ledger.adjust', $acc), [
        'amount' => '100000',
        'reason' => 'Penyesuaian audit kas',
    ]);
    $adjustResp->assertRedirect();

    // Show transaction detail
    $latestTx = LedgerTransaction::latest('id')->first();
    $showResp = $this->actingAs($this->admin)->get(route('admin.ledger.show', $latestTx));
    $showResp->assertOk()
        ->assertSee('Jurnal Pembukuan Berpasangan');
});
