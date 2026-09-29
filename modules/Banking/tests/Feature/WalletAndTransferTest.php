<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Banking\Application\Actions\SetPinAction;
use Modules\Banking\Application\Actions\TopUpAction;
use Modules\Banking\Application\Actions\TransferAction;
use Modules\Banking\Domain\Exceptions\InsufficientFundsException;
use Modules\Banking\Domain\Exceptions\SelfTransferException;
use Modules\Banking\Domain\Models\LedgerAccount;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Modules\Banking\database\seeders\BankingSeeder']);

    $this->sender = User::create([
        'name' => 'Budi Pengirim',
        'email' => 'budi.sender@example.com',
        'password' => Hash::make('secret'),
        'role' => 'customer',
    ]);

    $this->recipient = User::create([
        'name' => 'Siti Penerima',
        'email' => 'siti.recipient@example.com',
        'password' => Hash::make('secret'),
        'role' => 'customer',
    ]);

    app(SetPinAction::class)->execute($this->sender, '123456');
    app(SetPinAction::class)->execute($this->recipient, '654321');

    $this->topUpAction = app(TopUpAction::class);
    $this->transferAction = app(TransferAction::class);
});

test('maskName menyamarkan nama pengguna sesuai format', function () {
    $masked = TransferAction::maskName('Budi Santoso');
    expect($masked)->toContain('Bu')
        ->and($masked)->toContain('Sa')
        ->and($masked)->toContain('*');
});

test('top up saldo menambah saldo dompet pengguna dan tercatat di ledger', function () {
    $tx = $this->topUpAction->execute($this->sender, '1500000');

    expect($tx)->not->toBeNull()
        ->and($this->sender->walletBalance('IDR')->amount->toInt())->toBe(1500000);
});

test('top up menolak nominal negatif, nol, atau melebihi 50 juta', function () {
    expect(fn () => $this->topUpAction->execute($this->sender, '0'))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $this->topUpAction->execute($this->sender, '50000001'))
        ->toThrow(InvalidArgumentException::class);
});

test('transfer di bawah atau sama dengan 1 juta rupiah bebas biaya admin', function () {
    $this->topUpAction->execute($this->sender, '2000000');

    $tx = $this->transferAction->execute(
        sender: $this->sender,
        recipient: $this->recipient,
        amount: '500000',
        pin: '123456',
        note: 'Uang makan',
    );

    expect($tx->entries)->toHaveCount(2);

    $senderBal = $this->sender->walletBalance('IDR');
    $recipientBal = $this->recipient->walletBalance('IDR');

    expect($senderBal->amount->toInt())->toBe(1500000)
        ->and($recipientBal->amount->toInt())->toBe(500000);
});

test('transfer di atas 1 juta rupiah dikenakan biaya admin Rp 2500 dan menghasilkan 3 entri ledger seimbang', function () {
    $this->topUpAction->execute($this->sender, '3000000');

    $feeAccBefore = LedgerAccount::where('code', 'fee:banking:IDR')->first()->cached_balance;

    $tx = $this->transferAction->execute(
        sender: $this->sender,
        recipient: $this->recipient,
        amount: '1500000',
        pin: '123456',
        note: 'Transfer besar dengan fee',
    );

    expect($tx->entries)->toHaveCount(3);

    // Pengirim dipotong 1.500.000 + 2.500 = 1.502.500
    // Saldo awal 3.000.000 -> sisa 1.497.500
    $senderBal = $this->sender->walletBalance('IDR');
    $recipientBal = $this->recipient->walletBalance('IDR');

    expect($senderBal->amount->toInt())->toBe(1497500)
        ->and($recipientBal->amount->toInt())->toBe(1500000);

    $feeAccAfter = LedgerAccount::where('code', 'fee:banking:IDR')->first()->cached_balance;
    expect((float) $feeAccAfter)->toBe((float) $feeAccBefore + 2500);
});

test('transfer ke diri sendiri ditolak dengan SelfTransferException', function () {
    $this->topUpAction->execute($this->sender, '1000000');

    $this->transferAction->execute(
        sender: $this->sender,
        recipient: $this->sender,
        amount: '100000',
        pin: '123456',
    );
})->throws(SelfTransferException::class);

test('simulasi transfer konkuren/berurutan yang melebihi saldo ditolak dan tidak boleh bersaldo negatif', function () {
    // Saldo awal 5 juta
    $this->topUpAction->execute($this->sender, '5000000');

    // Transfer 1: 4 juta (+ fee 2.500 = 4.002.500) -> Sukses! Sisa: 997.500
    $this->transferAction->execute(
        sender: $this->sender,
        recipient: $this->recipient,
        amount: '4000000',
        pin: '123456',
    );

    expect($this->sender->walletBalance('IDR')->amount->toInt())->toBe(997500);

    // Transfer 2: mencoba transfer 2 juta (melebihi sisa 997.500) -> Harus gagal InsufficientFundsException!
    expect(function () {
        $this->transferAction->execute(
            sender: $this->sender,
            recipient: $this->recipient,
            amount: '2000000',
            pin: '123456',
        );
    })->toThrow(InsufficientFundsException::class);

    // Pastikan saldo sender tetap utuh 997.500 dan tidak pernah negatif
    expect($this->sender->walletBalance('IDR')->amount->toInt())->toBe(997500);
});
