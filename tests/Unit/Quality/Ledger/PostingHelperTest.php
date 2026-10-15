<?php

declare(strict_types=1);

use Brick\Math\BigDecimal;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Exceptions\UnbalancedTransactionException;
use Modules\Banking\Support\Posting;
use Modules\Banking\Support\PostingLines;
use Modules\Shared\Domain\ValueObjects\Money;

it('maps every AccountKind to its correct normal side', function (): void {
    $expectedSides = [
        AccountKind::ASSET->value => 'debit',
        AccountKind::CASH->value => 'debit',
        AccountKind::INVENTORY->value => 'debit',
        AccountKind::LOAN_RECEIVABLE->value => 'debit',
        AccountKind::EXPENSE->value => 'debit',

        AccountKind::REVENUE->value => 'credit',
        AccountKind::FEE->value => 'credit',
        AccountKind::LIABILITY->value => 'credit',
        AccountKind::AP->value => 'credit',
        AccountKind::DEPOSIT->value => 'credit',
        AccountKind::ESCROW->value => 'credit',
        AccountKind::WALLET->value => 'credit',
        AccountKind::POINTS->value => 'credit',
        AccountKind::CONTRA_ASSET->value => 'credit',
        AccountKind::COLLATERAL->value => 'credit',

        AccountKind::CLEARING->value => 'free',
        AccountKind::EXCHANGE->value => 'free',
    ];

    expect(count(AccountKind::cases()))->toBe(17);

    foreach (AccountKind::cases() as $kind) {
        expect($kind->normalSide())->toBe($expectedSides[$kind->value]);
        expect($kind->isDebitNormal())->toBe($expectedSides[$kind->value] === 'debit');
        expect($kind->isCreditNormal())->toBe($expectedSides[$kind->value] === 'credit');
        expect($kind->isFree())->toBe($expectedSides[$kind->value] === 'free');
    }
});

it('enforces single sign convention with debit as negative and credit as positive', function (): void {
    $entries = Posting::lines()
        ->debit('ast:cash:IDR', 1000)
        ->credit('rev:service:IDR', 1000)
        ->toEntries();

    expect($entries)->toHaveCount(2);

    expect($entries[0]->accountCode)->toBe('ast:cash:IDR');
    expect($entries[0]->amount->isEqualTo(-1000))->toBeTrue();

    expect($entries[1]->accountCode)->toBe('rev:service:IDR');
    expect($entries[1]->amount->isEqualTo(1000))->toBeTrue();
});

it('rejects posting nominal less than or equal to zero', function (): void {
    expect(fn () => Posting::lines()->debit('ast:cash:IDR', 0))
        ->toThrow(InvalidArgumentException::class, 'Nominal posting harus lebih besar dari 0.');

    expect(fn () => Posting::lines()->debit('ast:cash:IDR', -500))
        ->toThrow(InvalidArgumentException::class, 'Nominal posting harus lebih besar dari 0.');

    expect(fn () => Posting::lines()->credit('rev:service:IDR', 0))
        ->toThrow(InvalidArgumentException::class, 'Nominal posting harus lebih besar dari 0.');

    expect(fn () => Posting::lines()->credit('rev:service:IDR', -500))
        ->toThrow(InvalidArgumentException::class, 'Nominal posting harus lebih besar dari 0.');
});

it('throws UnbalancedTransactionException when sum of entries is not zero', function (): void {
    expect(fn () => Posting::lines()
        ->debit('ast:cash:IDR', 1000)
        ->credit('rev:service:IDR', 800)
        ->toEntries()
    )->toThrow(UnbalancedTransactionException::class);
});

it('requires at least two entries for a posting transaction', function (): void {
    expect(fn () => (new PostingLines)->toEntries())
        ->toThrow(InvalidArgumentException::class, 'Transaksi ledger harus memiliki minimal 2 entri.');
});

it('preserves the order of posting lines strictly', function (): void {
    $entriesDebitFirst = Posting::lines()
        ->debit('ast:cash:IDR', 500)
        ->credit('rev:service:IDR', 500)
        ->toEntries();

    expect($entriesDebitFirst[0]->accountCode)->toBe('ast:cash:IDR');
    expect($entriesDebitFirst[1]->accountCode)->toBe('rev:service:IDR');

    $entriesCreditFirst = Posting::lines()
        ->credit('rev:service:IDR', 500)
        ->debit('ast:cash:IDR', 500)
        ->toEntries();

    expect($entriesCreditFirst[0]->accountCode)->toBe('rev:service:IDR');
    expect($entriesCreditFirst[1]->accountCode)->toBe('ast:cash:IDR');
});

it('supports Money and BigDecimal objects in debit and credit', function (): void {
    $moneyDebit = Money::IDR(2500);
    $decimalCredit = BigDecimal::of('2500');

    $entries = Posting::lines()
        ->debit('ast:cash:IDR', $moneyDebit)
        ->credit('rev:service:IDR', $decimalCredit)
        ->toEntries();

    expect($entries[0]->amount->isEqualTo(-2500))->toBeTrue();
    expect($entries[1]->amount->isEqualTo(2500))->toBeTrue();
});
