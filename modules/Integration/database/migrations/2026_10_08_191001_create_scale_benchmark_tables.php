<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 191.1: Seeder checkpointing for 30 lines ultra dataset
        Schema::create('scl_seeder_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->string('domain_code')->unique(); // L01 - L30
            $table->integer('total_records_seeded')->default(0);
            $table->decimal('total_ledger_debit_idr', 18, 2)->default(0.00);
            $table->decimal('total_ledger_credit_idr', 18, 2)->default(0.00);
            $table->string('status')->default('COMPLETED'); // IN_PROGRESS, COMPLETED
            $table->timestamps();
        });

        // 191.2: Scale & Benchmark metric results across domains
        Schema::create('scl_benchmark_results', function (Blueprint $table) {
            $table->id();
            $table->string('benchmark_key')->unique();
            $table->string('domain_code');
            $table->string('operation_name'); // e.g. INGEST_TELEMATICS, BATCH_SETTLEMENT
            $table->integer('records_processed');
            $table->decimal('elapsed_ms', 10, 2);
            $table->decimal('peak_memory_mb', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scl_benchmark_results');
        Schema::dropIfExists('scl_seeder_checkpoints');
    }
};
