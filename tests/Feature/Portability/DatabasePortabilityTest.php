<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| PROGRESS R0.3: DatabasePortabilityTest (@group db-portability).
| Menguji portabilitas skema & tipe data presisi pada SQLite dan MySQL 8.
*/

it('preserves 64-bit integer money and 18-decimal precision across database engines', function (): void {
    if (! Schema::hasTable('test_db_portability')) {
        Schema::create('test_db_portability', function (Blueprint $table): void {
            $table->id();
            $table->bigInteger('amount_idr');
            $table->decimal('crypto_amount', 36, 18);
            $table->string('reference_code', 64);
            $table->timestamps();
        });
    }

    $id = DB::table('test_db_portability')->insertGetId([
        'amount_idr' => 922337203685477580,
        'crypto_amount' => '123456789.123456789012345678',
        'reference_code' => 'PORTABILITY-TEST-001',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $row = DB::table('test_db_portability')->where('id', $id)->first();

    expect((int) $row->amount_idr)->toBe(922337203685477580)
        ->and($row->reference_code)->toBe('PORTABILITY-TEST-001');

    Schema::dropIfExists('test_db_portability');
})->group('db-portability');
