<?php

declare(strict_types=1);

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerTransaction;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(LedgerAccountsSeeder::class);
    $this->seed(BankingSeeder::class);
});

test('two database connections racing same idempotency key return winner without double posting', function (): void {
    $sqliteFile = storage_path('framework/testing/ledger-concurrency-'.uniqid().'.sqlite');
    if (! is_dir(dirname($sqliteFile))) {
        mkdir(dirname($sqliteFile), 0777, true);
    }
    touch($sqliteFile);

    $defaultConn = DB::getDefaultConnection();
    config([
        'database.connections.concurrency_a' => [
            'driver' => 'sqlite',
            'database' => $sqliteFile,
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => 5000,
        ],
        'database.connections.concurrency_b' => [
            'driver' => 'sqlite',
            'database' => $sqliteFile,
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => 5000,
        ],
    ]);

    try {
        Artisan::call('migrate', ['--database' => 'concurrency_a', '--force' => true]);
        DB::connection('concurrency_a')->statement('PRAGMA journal_mode = WAL;');
        DB::connection('concurrency_a')->statement('PRAGMA busy_timeout = 5000;');
        DB::connection('concurrency_b')->statement('PRAGMA busy_timeout = 5000;');

        DB::setDefaultConnection('concurrency_a');
        Artisan::call('db:seed', ['--class' => LedgerAccountsSeeder::class, '--database' => 'concurrency_a', '--force' => true]);
        Artisan::call('db:seed', ['--class' => BankingSeeder::class, '--database' => 'concurrency_a', '--force' => true]);

        $acc1 = DB::connection('concurrency_a')->table('bank_ledger_accounts')->where('code', 'clearing:external:IDR')->first();
        $acc2 = DB::connection('concurrency_a')->table('bank_ledger_accounts')->where('code', 'fee:banking:IDR')->first();

        $dto = new PostingDTO(
            idempotencyKey: 'race_test_key_'.uniqid(),
            type: TransactionType::TRANSFER->value,
            entries: [
                PostingEntryDTO::forAccount($acc1->id, 'IDR', -50000),
                PostingEntryDTO::forAccount($acc2->id, 'IDR', 50000),
            ],
            description: 'Race test idempotency',
        );

        $service = new class extends LedgerService
        {
            protected function executeTransaction(callable $callback): mixed
            {
                return $callback();
            }
        };

        $payloadHash = (new ReflectionMethod($service, 'computePayloadHash'))->invoke($service, $dto);

        $raceTriggered = false;
        DB::connection('concurrency_a')->listen(function ($query) use ($dto, $payloadHash, $acc1, $acc2, &$raceTriggered): void {
            if (! $raceTriggered && str_contains(strtolower($query->sql), 'bank_ledger_transactions') && str_contains(strtolower($query->sql), 'idempotency_key')) {
                $raceTriggered = true;
                $now = now();
                $winnerId = DB::connection('concurrency_b')->table('bank_ledger_transactions')->insertGetId([
                    'uuid' => (string) Str::uuid(),
                    'idempotency_key' => $dto->idempotencyKey,
                    'payload_hash' => $payloadHash,
                    'type' => $dto->type,
                    'description' => $dto->description,
                    'posted_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::connection('concurrency_b')->table('bank_ledger_entries')->insert([
                    [
                        'transaction_id' => $winnerId,
                        'account_id' => $acc1->id,
                        'asset_code' => 'IDR',
                        'amount' => '-50000',
                        'balance_after' => '-50000',
                        'created_at' => $now,
                    ],
                    [
                        'transaction_id' => $winnerId,
                        'account_id' => $acc2->id,
                        'asset_code' => 'IDR',
                        'amount' => '50000',
                        'balance_after' => '50000',
                        'created_at' => $now,
                    ],
                ]);

                DB::connection('concurrency_b')->table('bank_ledger_accounts')->where('id', $acc1->id)->update(['cached_balance' => '-50000']);
                DB::connection('concurrency_b')->table('bank_ledger_accounts')->where('id', $acc2->id)->update(['cached_balance' => '50000']);
            }
        });

        $resultTx = $service->post($dto);

        expect($resultTx)->toBeInstanceOf(LedgerTransaction::class);
        expect($resultTx->idempotency_key)->toBe($dto->idempotencyKey);

        $count = DB::connection('concurrency_a')->table('bank_ledger_transactions')->where('idempotency_key', $dto->idempotencyKey)->count();
        expect($count)->toBe(1);

        $entryCount = DB::connection('concurrency_a')->table('bank_ledger_entries')->where('transaction_id', $resultTx->id)->count();
        expect($entryCount)->toBe(2);

        $finalAcc1 = DB::connection('concurrency_a')->table('bank_ledger_accounts')->where('id', $acc1->id)->first();
        $finalAcc2 = DB::connection('concurrency_a')->table('bank_ledger_accounts')->where('id', $acc2->id)->first();
        expect((string) $finalAcc1->cached_balance)->toBe('-50000');
        expect((string) $finalAcc2->cached_balance)->toBe('50000');
    } finally {
        DB::setDefaultConnection($defaultConn);
        DB::purge('concurrency_a');
        DB::purge('concurrency_b');
        @unlink($sqliteFile);
    }
});

test('ledger service locks accounts in deterministic ascending id order to prevent deadlock', function (): void {
    $accClearing = LedgerAccount::where('code', 'clearing:external:IDR')->firstOrFail();
    $accFee = LedgerAccount::where('code', 'fee:banking:IDR')->firstOrFail();

    $entryClearing = PostingEntryDTO::forAccount($accClearing->id, 'IDR', -10000);
    $entryFee = PostingEntryDTO::forAccount($accFee->id, 'IDR', 10000);

    // Arrange entries in strictly descending account_id order
    $entriesDesc = $accClearing->id > $accFee->id
        ? [$entryClearing, $entryFee]
        : [$entryFee, $entryClearing];

    $service = new LedgerService;

    $dtoDesc = new PostingDTO(
        idempotencyKey: 'lock_order_desc_'.uniqid(),
        type: TransactionType::TRANSFER->value,
        entries: $entriesDesc,
        description: 'Test lock order descending',
    );

    $capturedQueries = [];
    DB::listen(function ($query) use (&$capturedQueries): void {
        $sql = strtolower($query->sql);
        if (str_contains($sql, 'bank_ledger_accounts') && str_contains($sql, 'order by')) {
            $capturedQueries[] = $sql;
        }
    });

    $service->post($dtoDesc);

    expect($capturedQueries)->not->toBeEmpty();
    $foundAscendingOrder = false;
    foreach ($capturedQueries as $sql) {
        if (preg_match('/order\s+by\s+["`]?id["`]?\s+asc/i', $sql)) {
            $foundAscendingOrder = true;
            break;
        }
    }
    expect($foundAscendingOrder)->toBeTrue();
});

test('mysql two pdo connections lock for update blocks and serializes concurrent posting', function (): void {
    expect(DB::connection()->getDriverName())
        ->not->toBe('sqlite', 'Test db-portability DILARANG dijalankan di SQLite. Wajib dijalankan pada MySQL 8 / PostgreSQL di CI.');

    $accounts = LedgerAccount::whereIn('code', ['clearing:external:IDR', 'fee:banking:IDR'])->get();
    expect($accounts)->toHaveCount(2);

    $pdo1 = DB::connection()->getPdo();
    $config = config('database.connections.'.config('database.default'));
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s', $config['host'], $config['port'], $config['database']);
    $pdo2 = new PDO($dsn, $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $pdo1->beginTransaction();
    $stmt1 = $pdo1->prepare('SELECT * FROM bank_ledger_accounts WHERE id = :id FOR UPDATE');
    $stmt1->execute(['id' => $accounts[0]->id]);

    $lockedOut = false;
    try {
        $stmt2 = $pdo2->prepare('SELECT * FROM bank_ledger_accounts WHERE id = :id FOR UPDATE NOWAIT');
        $stmt2->execute(['id' => $accounts[0]->id]);
    } catch (PDOException) {
        $lockedOut = true;
    }

    $pdo1->commit();
    expect($lockedOut)->toBeTrue();
})->group('db-portability');
