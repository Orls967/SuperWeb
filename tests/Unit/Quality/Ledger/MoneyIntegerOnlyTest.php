<?php

declare(strict_types=1);

use Brick\Math\BigDecimal;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Shared\Domain\ValueObjects\Money;

it('Money rejects float values', function (): void {
    expect(fn () => Money::of('IDR', 1000.50))
        ->toThrow(TypeError::class);

    expect(fn () => Money::IDR(50000.75))
        ->toThrow(TypeError::class);

    expect(fn () => Money::fromIdr(100.25))
        ->toThrow(TypeError::class);

    $money = Money::IDR(1000);
    expect(fn () => $money->multiply(1.5))
        ->toThrow(TypeError::class);
});

it('Money accepts int, string, and BigDecimal', function (): void {
    $mInt = Money::IDR(10000);
    expect((string) $mInt->amount)->toBe('10000');

    $mStr = Money::of('BTC', '0.00005000');
    expect((string) $mStr->amount)->toBe('0.00005000');

    $mBd = Money::of('USD', BigDecimal::of('12.50'));
    expect((string) $mBd->amount)->toBe('12.50');

    $multiplied = $mInt->multiply(2);
    expect((string) $multiplied->amount)->toBe('20000');

    $multipliedStr = $mInt->multiply('3');
    expect((string) $multipliedStr->amount)->toBe('30000');
});

it('PostingEntryDTO rejects float and accepts valid types', function (): void {
    expect(fn () => new PostingEntryDTO(1, 'ACC1', 'IDR', 100.5))
        ->toThrow(TypeError::class);

    expect(fn () => PostingEntryDTO::forAccount(1, 'IDR', 250.0))
        ->toThrow(TypeError::class);

    expect(fn () => PostingEntryDTO::forCode('ACC1', 'IDR', 12.34))
        ->toThrow(TypeError::class);

    $entryInt = PostingEntryDTO::forAccount(1, 'IDR', 1000);
    expect((string) $entryInt->amount)->toBe('1000');

    $entryStr = PostingEntryDTO::forCode('ACC1', 'BTC', '0.00001000');
    expect((string) $entryStr->amount)->toBe('0.00001000');

    $entryMoney = PostingEntryDTO::forAccount(1, 'IDR', Money::IDR(5000));
    expect((string) $entryMoney->amount)->toBe('5000');
});

it('LedgerAccount canCover accepts int, string, and BigDecimal', function (): void {
    $account = new LedgerAccount([
        'cached_balance' => '100000',
        'allow_negative' => false,
    ]);

    expect($account->canCover(50000))->toBeTrue();
    expect($account->canCover('100000'))->toBeTrue();
    expect($account->canCover(BigDecimal::of(100001)))->toBeFalse();
});
