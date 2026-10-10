<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| PROGRESS R0.3: DatabasePortabilityTest (@group db-portability).
| Menguji portabilitas skema & tipe data presisi pada MySQL 8 / PostgreSQL di CI.
| Wajib GAGAL bila dijalankan pada SQLite (bukan skip).
*/

beforeEach(function (): void {
    expect(DB::connection()->getDriverName())
        ->not->toBe('sqlite', 'Test db-portability DILARANG dijalankan di SQLite. Wajib dijalankan pada MySQL 8 / PostgreSQL di CI.');
});

it('preserves 64-bit integer money and 18-decimal precision round-trip', function (): void {
    if (! Schema::hasTable('test_db_portability')) {
        Schema::create('test_db_portability', function (Blueprint $table): void {
            $table->id();
            $table->bigInteger('amount_idr');
            $table->decimal('crypto_amount', 36, 18);
            $table->string('reference_code', 64);
            $table->timestamps();
        });
    }

    $exactDecimal = '123456789012345678.123456789012345678';
    $id = DB::table('test_db_portability')->insertGetId([
        'amount_idr' => 922337203685477580,
        'crypto_amount' => $exactDecimal,
        'reference_code' => 'PORTABILITY-TEST-001',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $row = DB::table('test_db_portability')->where('id', $id)->first();

    expect((int) $row->amount_idr)->toBe(922337203685477580)
        ->and((string) $row->crypto_amount)->toBe($exactDecimal)
        ->and($row->reference_code)->toBe('PORTABILITY-TEST-001');

    Schema::dropIfExists('test_db_portability');
})->group('db-portability');

it('rejects values exceeding column length in strict mode', function (): void {
    if (! Schema::hasTable('test_db_portability_overflow')) {
        Schema::create('test_db_portability_overflow', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 10);
        });
    }

    expect(function (): void {
        DB::table('test_db_portability_overflow')->insert([
            'code' => 'STRING_LEBIH_DARI_10_KARAKTER',
        ]);
    })->toThrow(QueryException::class);

    Schema::dropIfExists('test_db_portability_overflow');
})->group('db-portability');

it('enforces lockForUpdate holding second connection until released', function (): void {
    if (! Schema::hasTable('test_db_portability_lock')) {
        Schema::create('test_db_portability_lock', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
    }

    $id = DB::table('test_db_portability_lock')->insertGetId([
        'name' => 'initial',
    ]);

    $defaultConfig = config('database.connections.'.config('database.default'));
    $dsn = sprintf(
        '%s:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $defaultConfig['driver'],
        $defaultConfig['host'],
        $defaultConfig['port'],
        $defaultConfig['database']
    );
    $pdo2 = new PDO(
        $dsn,
        $defaultConfig['username'],
        $defaultConfig['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 1]
    );

    // Connection 1 begins transaction and acquires exclusive row lock
    DB::beginTransaction();
    DB::table('test_db_portability_lock')->where('id', $id)->lockForUpdate()->first();

    // Connection 2 attempts to acquire lock immediately with NOWAIT -> must fail
    $lockFailed = false;
    try {
        $pdo2->beginTransaction();
        $stmt = $pdo2->prepare('SELECT * FROM test_db_portability_lock WHERE id = :id FOR UPDATE NOWAIT');
        $stmt->execute(['id' => $id]);
    } catch (Throwable) {
        $lockFailed = true;
        if ($pdo2->inTransaction()) {
            $pdo2->rollBack();
        }
    }

    expect($lockFailed)->toBeTrue('Koneksi kedua wajib ditahan/gagal saat baris sedang dikunci oleh lockForUpdate koneksi pertama.');

    // Connection 1 releases lock
    DB::rollBack();

    // Positive control: Connection 2 acquires lock successfully after lock is released
    $pdo2->beginTransaction();
    $stmt2 = $pdo2->prepare('SELECT * FROM test_db_portability_lock WHERE id = :id FOR UPDATE NOWAIT');
    $stmt2->execute(['id' => $id]);
    $lockedRow = $stmt2->fetch(PDO::FETCH_ASSOC);
    $pdo2->rollBack();

    expect($lockedRow)->not->toBeFalse('Kontrol positif: Koneksi kedua wajib berhasil mengunci baris setelah lock koneksi pertama dilepas.');

    Schema::dropIfExists('test_db_portability_lock');
})->group('db-portability');
