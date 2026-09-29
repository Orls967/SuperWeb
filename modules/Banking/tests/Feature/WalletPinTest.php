<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Banking\Application\Actions\SetPinAction;
use Modules\Banking\Application\Actions\VerifyPinAction;
use Modules\Banking\Domain\Exceptions\InvalidPinException;
use Modules\Banking\Domain\Exceptions\PinLockedException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::create([
        'name' => 'Test User PIN',
        'email' => 'user.pin@example.com',
        'password' => Hash::make('password'),
        'role' => 'customer',
    ]);

    $this->setPin = app(SetPinAction::class);
    $this->verifyPin = app(VerifyPinAction::class);
});

test('set PIN memvalidasi panjang tepat 6 digit angka', function () {
    expect(fn () => $this->setPin->execute($this->user, '12345'))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $this->setPin->execute($this->user, 'abcdef'))
        ->toThrow(InvalidArgumentException::class);

    $pin = $this->setPin->execute($this->user, '123456');
    expect($pin)->not->toBeNull()
        ->and($pin->user_id)->toBe($this->user->id);
});

test('verifikasi PIN benar berhasil dan me-reset failed attempts', function () {
    $this->setPin->execute($this->user, '123456');

    $result = $this->verifyPin->execute($this->user, '123456');
    expect($result)->toBeTrue();
});

test('verifikasi PIN salah 5 kali mengunci akun selama 15 menit', function () {
    $this->setPin->execute($this->user, '123456');

    // 4 kali salah -> InvalidPinException
    for ($i = 1; $i <= 4; $i++) {
        try {
            $this->verifyPin->execute($this->user, '999999');
            $this->fail('Harusnya melempar InvalidPinException');
        } catch (InvalidPinException $e) {
            expect($e->failedAttempts)->toBe($i);
        }
    }

    // Percobaan ke-5 -> Harus terkunci (PinLockedException)
    expect(fn () => $this->verifyPin->execute($this->user, '999999'))
        ->toThrow(PinLockedException::class);

    // Percobaan berikutnya meskipun PIN benar tetap terkunci
    expect(fn () => $this->verifyPin->execute($this->user, '123456'))
        ->toThrow(PinLockedException::class);
});
