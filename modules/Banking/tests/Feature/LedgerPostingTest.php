<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Exceptions\AccountFrozenException;
use Modules\Banking\Domain\Exceptions\AssetMismatchException;
use Modules\Banking\Domain\Exceptions\IdempotencyConflictException;
use Modules\Banking\Domain\Exceptions\ImmutableRecordException;
use Modules\Banking\Domain\Exceptions\InsufficientFundsException;
use Modules\Banking\Domain\Exceptions\UnbalancedTransactionException;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerEntry;
use Modules\Banking\Domain\Models\LedgerTransaction;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Modules\Banking\database\seeders\BankingSeeder']);
    $this->ledger = app(Ledger::class);
});

test('posting seimbang berhasil dan memperbarui saldo rekening dengan benar', function () {
    $user = User::factory()->create();
    $userAcc = $user->walletAccount('IDR');

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
    $user = User::factory()->create();
    $userAcc = $user->walletAccount('IDR');

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
    $user = User::factory()->create();
    $userAcc = $user->walletAccount('IDR');
    $userAcc->forceFill(['cached_balance' => '100000'])->save();

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
    expect(Schema::getColumnType('bank_ledger_transactions', 'reference_id'))
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
    $user = User::factory()->create();
    $userAcc = $user->walletAccount('IDR');
    $userAcc->forceFill(['cached_balance' => '500000', 'is_frozen' => true])->save();

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
    $user = User::factory()->create();
    $userAcc = $user->walletAccount('IDR');

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

test('posting dengan asset_code entri tidak cocok dengan aset akun melempar AssetMismatchException', function () {
    $user = User::factory()->create();
    $userAcc = $user->walletAccount('IDR');

    // Kedua entri memakai assetCode 'BTC' (seimbang 0), namun akun bertipe aset 'IDR'
    $dto = new PostingDTO(
        type: TransactionType::TOPUP->value,
        description: 'Asset mismatch tx',
        idempotencyKey: 'test_asset_mismatch_btc_idr',
        entries: [
            PostingEntryDTO::forCode('clearing:external:IDR', 'BTC', -1),
            PostingEntryDTO::forAccount($userAcc->id, 'BTC', 1),
        ],
    );

    $this->ledger->post($dto);
})->throws(AssetMismatchException::class);

test('posting ulang dengan key sama dan payload beda melempar IdempotencyConflictException', function () {
    $user = User::factory()->create();
    $userAcc = $user->walletAccount('IDR');

    $dto1 = new PostingDTO(
        type: TransactionType::TOPUP->value,
        description: 'First attempt',
        idempotencyKey: 'conflict_key_123',
        entries: [
            PostingEntryDTO::forCode('clearing:external:IDR', 'IDR', -100000),
            PostingEntryDTO::forAccount($userAcc->id, 'IDR', 100000),
        ],
    );

    $tx1 = $this->ledger->post($dto1);
    expect($tx1->payload_hash)->not->toBeNull();

    // Payload berbeda (nominal 200000 vs 100000) dengan idempotency key yang sama
    $dto2 = new PostingDTO(
        type: TransactionType::TOPUP->value,
        description: 'Second attempt with different amount',
        idempotencyKey: 'conflict_key_123',
        entries: [
            PostingEntryDTO::forCode('clearing:external:IDR', 'IDR', -200000),
            PostingEntryDTO::forAccount($userAcc->id, 'IDR', 200000),
        ],
    );

    $this->ledger->post($dto2);
})->throws(IdempotencyConflictException::class);

test('unique violation saat race condition ditangkap dan mengembalikan transaksi pemenang', function () {
    $user = User::factory()->create();
    $userAcc = $user->walletAccount('IDR');

    $dto = new PostingDTO(
        type: TransactionType::TOPUP->value,
        description: 'Simulated race transaction',
        idempotencyKey: 'race_simulation_key_001',
        entries: [
            PostingEntryDTO::forCode('clearing:external:IDR', 'IDR', -50000),
            PostingEntryDTO::forAccount($userAcc->id, 'IDR', 50000),
        ],
    );

    // 1. Pemenang (transaksi pertama) berhasil diposting
    $winner = $this->ledger->post($dto);

    // 2. Simulasi proses kedua yang mengalami race condition dan menabrak unique constraint saat insert
    $simulatedService = new class extends LedgerService
    {
        public bool $triggerUnique = true;

        protected function executeTransaction(callable $callback): mixed
        {
            if ($this->triggerUnique) {
                $this->triggerUnique = false;
                $pdoEx = new PDOException('UNIQUE constraint failed: bank_ledger_transactions.idempotency_key', 23000);
                throw new QueryException('sqlite', 'insert into bank_ledger_transactions...', [], $pdoEx);
            }

            return DB::transaction($callback);
        }
    };

    $result = $simulatedService->post($dto);

    expect($result)->toBeInstanceOf(LedgerTransaction::class)
        ->and($result->id)->toBe($winner->id)
        ->and($result->idempotency_key)->toBe('race_simulation_key_001');
});

test('model LedgerTransaction melempar ImmutableRecordException pada updating dan deleting', function () {
    $user = User::factory()->create();
    $userAcc = $user->walletAccount('IDR');

    $dto = new PostingDTO(
        type: TransactionType::TOPUP->value,
        description: 'Immutable tx test',
        idempotencyKey: 'immutable_tx_key_001',
        entries: [
            PostingEntryDTO::forCode('clearing:external:IDR', 'IDR', -10000),
            PostingEntryDTO::forAccount($userAcc->id, 'IDR', 10000),
        ],
    );

    $tx = $this->ledger->post($dto);

    expect(fn () => $tx->update(['description' => 'Hacked']))
        ->toThrow(ImmutableRecordException::class)
        ->and(fn () => $tx->delete())
        ->toThrow(ImmutableRecordException::class);
});

test('model LedgerEntry melempar ImmutableRecordException pada updating dan deleting', function () {
    $user = User::factory()->create();
    $userAcc = $user->walletAccount('IDR');

    $dto = new PostingDTO(
        type: TransactionType::TOPUP->value,
        description: 'Immutable entry test',
        idempotencyKey: 'immutable_entry_key_001',
        entries: [
            PostingEntryDTO::forCode('clearing:external:IDR', 'IDR', -20000),
            PostingEntryDTO::forAccount($userAcc->id, 'IDR', 20000),
        ],
    );

    $tx = $this->ledger->post($dto);
    $entry = $tx->entries->first();

    expect(fn () => $entry->update(['amount' => '999999']))
        ->toThrow(ImmutableRecordException::class)
        ->and(fn () => $entry->delete())
        ->toThrow(ImmutableRecordException::class);
});

test('database trigger melempar QueryException pada query builder update dan delete di bank_ledger_entries', function () {
    $user = User::factory()->create();
    $userAcc = $user->walletAccount('IDR');

    $dto = new PostingDTO(
        type: TransactionType::TOPUP->value,
        description: 'DB trigger append-only test',
        idempotencyKey: 'db_trigger_test_001',
        entries: [
            PostingEntryDTO::forCode('clearing:external:IDR', 'IDR', -20000),
            PostingEntryDTO::forAccount($userAcc->id, 'IDR', 20000),
        ],
    );

    $tx = $this->ledger->post($dto);
    $entry = $tx->entries->first();

    // Query builder update bypasses Eloquent events but is blocked by DB trigger
    expect(fn () => LedgerEntry::query()->where('id', $entry->id)->update(['amount' => '999999']))
        ->toThrow(QueryException::class);

    // Query builder delete bypasses Eloquent events but is blocked by DB trigger
    expect(fn () => LedgerEntry::query()->where('id', $entry->id)->delete())
        ->toThrow(QueryException::class);

    // Also verify bank_ledger_transactions trigger
    expect(fn () => LedgerTransaction::query()->where('id', $tx->id)->update(['description' => 'Hacked']))
        ->toThrow(QueryException::class);

    expect(fn () => LedgerTransaction::query()->where('id', $tx->id)->delete())
        ->toThrow(QueryException::class);
});

test('posting dengan tipe transaksi lebih dari 32 karakter melempar InvalidArgumentException', function () {
    $user = User::factory()->create();
    $userAcc = $user->walletAccount('IDR');

    $dto = new PostingDTO(
        type: str_repeat('A', 33),
        description: 'Type length exceeding 32 chars',
        idempotencyKey: 'type_len_key_001',
        entries: [
            PostingEntryDTO::forCode('clearing:external:IDR', 'IDR', -20000),
            PostingEntryDTO::forAccount($userAcc->id, 'IDR', 20000),
        ],
    );

    expect(fn () => $this->ledger->post($dto))
        ->toThrow(InvalidArgumentException::class, 'melebihi batas 32 karakter');
});

test('posting dengan tipe transaksi tidak terdaftar melempar InvalidArgumentException', function () {
    $user = User::factory()->create();
    $userAcc = $user->walletAccount('IDR');

    $dto = new PostingDTO(
        type: 'UNREGISTERED_TYPE_XYZ',
        description: 'Unregistered transaction type',
        idempotencyKey: 'unreg_type_key_001',
        entries: [
            PostingEntryDTO::forCode('clearing:external:IDR', 'IDR', -20000),
            PostingEntryDTO::forAccount($userAcc->id, 'IDR', 20000),
        ],
    );

    expect(fn () => $this->ledger->post($dto))
        ->toThrow(InvalidArgumentException::class, 'tidak terdaftar di registry');
});
