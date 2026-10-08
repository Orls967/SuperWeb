<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_endpoint_perf_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint');
            $table->string('http_method', 10);
            $table->decimal('p50_latency_ms', 8, 2);
            $table->decimal('p95_latency_ms', 8, 2);
            $table->decimal('p99_latency_ms', 8, 2);
            $table->decimal('throughput_rps', 8, 2);
            $table->integer('slow_query_count')->default(0);
            $table->boolean('detected_n_plus_one')->default(false);
            $table->decimal('budget_max_p95_ms', 8, 2)->default(200.00);
            $table->boolean('is_regression_failed')->default(false); // 239.1, 239.5
            $table->timestamps();
        });

        Schema::create('platform_cost_to_serve_allocations', function (Blueprint $table) {
            $table->id();
            $table->string('module_code')->index();
            $table->string('period_month', 7); // YYYY-MM
            $table->integer('transaction_count');
            $table->decimal('compute_cost_usd', 10, 2);
            $table->decimal('storage_cost_usd', 10, 2);
            $table->decimal('queue_cost_usd', 10, 2);
            $table->decimal('unit_cost_per_tx_usd', 8, 4);
            $table->decimal('savings_achieved_usd', 10, 2)->default(0.00);
            $table->timestamps();
        });

        Schema::create('platform_capacity_projections', function (Blueprint $table) {
            $table->id();
            $table->string('forecast_code')->unique();
            $table->integer('projected_months')->default(12);
            $table->decimal('projected_traffic_growth_pct', 5, 2);
            $table->string('recommended_scaling_action'); // SHARDING, READ_REPLICA, COLD_ARCHIVE
            $table->decimal('capex_estimate_usd', 12, 2);
            $table->decimal('opex_estimate_usd', 12, 2);
            $table->string('treasury_proposal_status')->default('PROPOSED'); // PROPOSED, SUBMITTED, APPROVED
            $table->timestamps();
        });

        Schema::create('platform_performance_tradeoffs', function (Blueprint $table) {
            $table->id();
            $table->string('feature_name');
            $table->string('optimization_type'); // READ_INDEXING, CACHING, CQRS_DENORMALIZATION
            $table->decimal('read_gain_pct', 5, 2);
            $table->decimal('write_penalty_pct', 5, 2); // 239.6 Edge case
            $table->text('decision_rationale');
            $table->string('approved_by');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_performance_tradeoffs');
        Schema::dropIfExists('platform_capacity_projections');
        Schema::dropIfExists('platform_cost_to_serve_allocations');
        Schema::dropIfExists('platform_endpoint_perf_metrics');
    }
};
