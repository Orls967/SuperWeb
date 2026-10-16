<?php

declare(strict_types=1);

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

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(LedgerAccountsSeeder::class);
    $this->seed(BankingSeeder::class);
});

test('eighteen decimal places survive database round trip via ledger service', function (): void {
    $user = User::factory()->create();
    $userBtc = $user->walletAccount('BTC');
    $clearing = LedgerAccount::where('code', 'clearing:external:BTC')->firstOrFail();

    $service = app(LedgerService::class);

    // 1. Uji presisi 18 desimal penuh: 123.456789012345678901
    $amount1 = '123.456789012345678901';
    $tx1 = $service->post(new PostingDTO(
        type: TransactionType::EXCHANGE->value,
        description: 'Uji presisi 18 desimal penuh',
        idempotencyKey: 'test:decimal:18:roundtrip:1',
        entries: [
            PostingEntryDTO::forAccount($clearing->id, 'BTC', BigDecimal::of($amount1)->negated()),
            PostingEntryDTO::forAccount($userBtc->id, 'BTC', BigDecimal::of($amount1)),
        ],
        referenceType: 'test_decimal',
        referenceId: 1,
    ));

    $entryUser1 = LedgerEntry::where('transaction_id', $tx1->id)
        ->where('account_id', $userBtc->id)
        ->firstOrFail();
    $entryClearing1 = LedgerEntry::where('transaction_id', $tx1->id)
        ->where('account_id', $clearing->id)
        ->firstOrFail();

    expect((string) $entryUser1->amount)->toBe($amount1)
        ->and((string) $entryUser1->balance_after)->toBe($amount1)
        ->and((string) $entryClearing1->amount)->toBe('-'.$amount1)
        ->and((string) $userBtc->fresh()->cached_balance)->toBe($amount1);

    // 2. Uji nilai 10^-18 (0.000000000000000001)
    $amount2 = '0.000000000000000001';
    $tx2 = $service->post(new PostingDTO(
        type: TransactionType::EXCHANGE->value,
        description: 'Uji nilai 10^-18',
        idempotencyKey: 'test:decimal:18:roundtrip:2',
        entries: [
            PostingEntryDTO::forAccount($clearing->id, 'BTC', BigDecimal::of($amount2)->negated()),
            PostingEntryDTO::forAccount($userBtc->id, 'BTC', BigDecimal::of($amount2)),
        ],
        referenceType: 'test_decimal',
        referenceId: 2,
    ));

    $entryUser2 = LedgerEntry::where('transaction_id', $tx2->id)
        ->where('account_id', $userBtc->id)
        ->firstOrFail();

    $expectedBalance = '123.456789012345678902';
    expect((string) $entryUser2->amount)->toBe($amount2)
        ->and((string) $entryUser2->balance_after)->toBe($expectedBalance)
        ->and((string) $userBtc->fresh()->cached_balance)->toBe($expectedBalance);

    // Reconcile wajib lulus tanpa selisih
    $this->artisan('bank:reconcile')->assertSuccessful();
});

test('mysql native decimal 36 18 round trip and native sum reconcile', function (): void {
    expect(DB::connection()->getDriverName())
        ->not->toBe('sqlite', 'Test db-portability DILARANG dijalankan di SQLite. Wajib dijalankan pada MySQL 8 / PostgreSQL di CI.');

    $user = User::factory()->create();
    $userBtc = $user->walletAccount('BTC');
    $clearing = LedgerAccount::where('code', 'clearing:external:BTC')->firstOrFail();

    $service = app(LedgerService::class);
    $amount = '987.654321098765432109';

    $tx = $service->post(new PostingDTO(
        type: TransactionType::EXCHANGE->value,
        description: 'Uji MySQL native decimal 36,18',
        idempotencyKey: 'test:decimal:mysql:native:1',
        entries: [
            PostingEntryDTO::forAccount($clearing->id, 'BTC', BigDecimal::of($amount)->negated()),
            PostingEntryDTO::forAccount($userBtc->id, 'BTC', BigDecimal::of($amount)),
        ],
        referenceType: 'test_decimal_mysql',
        referenceId: 1,
    ));

    $entry = LedgerEntry::where('transaction_id', $tx->id)
        ->where('account_id', $userBtc->id)
        ->firstOrFail();

    expect((string) $entry->amount)->toBe($amount)
        ->and((string) $userBtc->fresh()->cached_balance)->toBe($amount);

    $this->artisan('bank:reconcile')->assertSuccessful();
})->group('db-portability');
