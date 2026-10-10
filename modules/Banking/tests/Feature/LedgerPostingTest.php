<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Exceptions\AccountFrozenException;
use Modules\Banking\Domain\Exceptions\InsufficientFundsException;
use Modules\Banking\Domain\Exceptions\UnbalancedTransactionException;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerTransaction;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Modules\Banking\database\seeders\BankingSeeder']);
    $this->ledger = app(Ledger::class);
});

test('posting seimbang berhasil dan memperbarui saldo rekening dengan benar', function () {
    $userAcc = LedgerAccount::create([
        'uuid' => (string) Str::uuid(),
        'code' => 'wallet:user:999:IDR',
        'asset_code' => 'IDR',
        'kind' => AccountKind::WALLET->value,
        'name' => 'Dompet Test',
        'allow_negative' => false,
        'cached_balance' => '0',
    ]);

    $dto = new PostingDTO(
        type: TransactionType::TOPUP->value,
        description: 'Test Topup 500k',
        idempotencyKey: 'test_key_001',
        entries: [
            PostingEntryDTO::forCode('clearing:external:IDR', 'IDR', -500000),
            PostingEntryDTO::forAccount($userAcc->id, 'IDR', 500000),
        ],
    );

    $tx = $this->ledger->post($dto);

    expect($tx)->toBeInstanceOf(LedgerTransaction::class)
        ->and($tx->entries)->toHaveCount(2)
        ->and((float) $userAcc->fresh()->cached_balance)->toBe(500000.0);

    $clearing = LedgerAccount::where('code', 'clearing:external:IDR')->first();
    expect((float) $clearing->cached_balance)->not->toBe(0.0);
});

test('posting tidak seimbang melempar UnbalancedTransactionException', function () {
    $userAcc = LedgerAccount::create([
        'uuid' => (string) Str::uuid(),
        'code' => 'wallet:user:998:IDR',
        'asset_code' => 'IDR',
        'kind' => AccountKind::WALLET->value,
        'name' => 'Dompet Unbalanced Test',
        'allow_negative' => false,
        'cached_balance' => '0',
    ]);

    $dto = new PostingDTO(
        type: TransactionType::TOPUP->value,
        description: 'Unbalanced tx',
        idempotencyKey: 'test_unbalanced_key',
        entries: [
            PostingEntryDTO::forCode('clearing:external:IDR', 'IDR', -500000),
            PostingEntryDTO::forAccount($userAcc->id, 'IDR', 400000), // Selisih 100k!
        ],
    );

    $this->ledger->post($dto);
})->throws(UnbalancedTransactionException::class);

test('posting dengan kurang dari 2 entri melempar InvalidArgumentException', function () {
    $dto = new PostingDTO(
        type: TransactionType::TOPUP->value,
        description: 'Single entry',
        idempotencyKey: 'single_entry_key',
        entries: [
            PostingEntryDTO::forCode('clearing:external:IDR', 'IDR', 0),
        ],
    );

    $this->ledger->post($dto);
})->throws(InvalidArgumentException::class);

test('posting yang menyebabkan saldo negatif pada non allow_negative melempar InsufficientFundsException', function () {
    $userAcc = LedgerAccount::create([
        'uuid' => (string) Str::uuid(),
        'code' => 'wallet:user:997:IDR',
        'asset_code' => 'IDR',
        'kind' => AccountKind::WALLET->value,
        'name' => 'Dompet Miskin',
        'allow_negative' => false,
        'cached_balance' => '100000',
    ]);

    $dto = new PostingDTO(
        type: TransactionType::TRANSFER->value,
        description: 'Overdrawn tx',
        idempotencyKey: 'overdrawn_key',
        entries: [
            PostingEntryDTO::forAccount($userAcc->id, 'IDR', -150000),
            PostingEntryDTO::forCode('clearing:external:IDR', 'IDR', 150000),
        ],
    );

    $this->ledger->post($dto);
})->throws(InsufficientFundsException::class);

test('posting dengan reference UUID atau ULID string tersimpan dengan benar dan kolom bertipe string', function () {
    expect(\Illuminate\Support\Facades\Schema::getColumnType('bank_ledger_transactions', 'reference_id'))
        ->toBeIn(['string', 'varchar']);

    $userAcc = LedgerAccount::where('code', 'revenue:store:IDR')->firstOrFail();

    $ulidReference = '01a126fa-ae48-70e6-bbea-1c32c6f7a70e';
    $dto = new PostingDTO(
        type: TransactionType::MANUAL_ADJUSTMENT->value,
        description: 'Test UUID reference',
        idempotencyKey: 'test_uuid_ref_key',
        entries: [
            PostingEntryDTO::forCode('clearing:external:IDR', 'IDR', -1000),
            PostingEntryDTO::forAccount($userAcc->id, 'IDR', 1000),
        ],
        referenceType: 'Modules\Asset\Domain\Models\Asset',
        referenceId: $ulidReference,
    );

    $tx = $this->ledger->post($dto);

    expect($tx->reference_id)->toBe($ulidReference)
        ->and($tx->reference_type)->toBe('Modules\Asset\Domain\Models\Asset');
});

test('posting pada akun yang frozen melempar AccountFrozenException', function () {
    $userAcc = LedgerAccount::create([
        'uuid' => (string) Str::uuid(),
        'code' => 'wallet:user:996:IDR',
        'asset_code' => 'IDR',
        'kind' => AccountKind::WALLET->value,
        'name' => 'Dompet Beku',
        'allow_negative' => false,
        'cached_balance' => '500000',
        'is_frozen' => true,
    ]);

    $dto = new PostingDTO(
        type: TransactionType::TRANSFER->value,
        description: 'Frozen tx',
        idempotencyKey: 'frozen_key',
        entries: [
            PostingEntryDTO::forAccount($userAcc->id, 'IDR', -10000),
            PostingEntryDTO::forCode('clearing:external:IDR', 'IDR', 10000),
        ],
    );

    $this->ledger->post($dto);
})->throws(AccountFrozenException::class);

test('idempotency: posting ulang dengan key yang sama mengembalikan transaksi yang sama tanpa menambah saldo ganda', function () {
    $userAcc = LedgerAccount::create([
        'uuid' => (string) Str::uuid(),
        'code' => 'wallet:user:995:IDR',
        'asset_code' => 'IDR',
        'kind' => AccountKind::WALLET->value,
        'name' => 'Dompet Idempotent',
        'allow_negative' => false,
        'cached_balance' => '0',
    ]);

    $dto = new PostingDTO(
        type: TransactionType::TOPUP->value,
        description: 'First call',
        idempotencyKey: 'idempotent_test_key_abc',
        entries: [
            PostingEntryDTO::forCode('clearing:external:IDR', 'IDR', -250000),
            PostingEntryDTO::forAccount($userAcc->id, 'IDR', 250000),
        ],
    );

    $tx1 = $this->ledger->post($dto);
    expect((float) $userAcc->fresh()->cached_balance)->toBe(250000.0);

    // Post exactly same DTO again
    $tx2 = $this->ledger->post($dto);

    expect($tx2->id)->toBe($tx1->id)
        ->and($tx2->uuid)->toBe($tx1->uuid)
        ->and((float) $userAcc->fresh()->cached_balance)->toBe(250000.0); // Tidak berlipat ganda
});
