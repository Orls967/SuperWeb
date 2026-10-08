<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 192.1 & 192.2: Cold archive store with SHA-256 checksums
        Schema::create('scl_cold_archives', function (Blueprint $table) {
            $table->id();
            $table->string('archive_key')->unique();
            $table->string('table_source');
            $table->string('partition_period'); // e.g. 2021-01
            $table->integer('record_count');
            $table->string('checksum_sha256');
            $table->string('storage_location'); // S3_GLACIER, COLD_BLOB
            $table->string('status')->default('ARCHIVED'); // ARCHIVED, RECALLED
            $table->timestamps();
        });

        // 192.3: Materialized summaries (daily/monthly rollups per domain)
        Schema::create('scl_domain_rollups', function (Blueprint $table) {
            $table->id();
            $table->string('rollup_code')->unique();
            $table->string('domain_code');
            $table->string('period_date'); // YYYY-MM-DD
            $table->decimal('total_amount_idr', 18, 2);
            $table->integer('total_events');
            $table->timestamps();
        });

        // 192.4: Query budget registry (query count & p95 latency thresholds)
        Schema::create('scl_query_budgets', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint_name')->unique();
            $table->integer('max_queries_allowed');
            $table->decimal('max_p95_latency_ms', 8, 2);
            $table->integer('actual_queries_count')->default(0);
            $table->decimal('actual_p95_latency_ms', 8, 2)->default(0.00);
            $table->boolean('budget_breached')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scl_query_budgets');
        Schema::dropIfExists('scl_domain_rollups');
        Schema::dropIfExists('scl_cold_archives');
    }
};
