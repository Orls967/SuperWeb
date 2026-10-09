<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plt_efficiency_backlog_items', function (Blueprint $table) {
            $table->id();
            $table->string('item_code')->unique();
            $table->string('category'); // slow_query, storage, cache_miss, model_cost, queue_backlog
            $table->decimal('baseline_cost_per_month', 18, 2); // 434.2, 434.6 mandatory baseline
            $table->decimal('post_opt_cost_per_month', 18, 2)->nullable();
            $table->decimal('validated_monthly_savings', 18, 2)->default(0.00);
            $table->boolean('service_quality_tradeoff_approved')->default(true); // 434.5 edge case
            $table->string('status')->default('backlog'); // backlog, optimizing, validated
            $table->timestamps();
        });

        Schema::create('plt_feature_performance_budgets', function (Blueprint $table) {
            $table->id();
            $table->string('feature_code')->unique();
            $table->decimal('max_allowed_latency_ms', 10, 2); // 434.3 budget
            $table->decimal('max_allowed_memory_mb', 10, 2);
            $table->decimal('tested_latency_ms', 10, 2)->nullable();
            $table->decimal('tested_memory_mb', 10, 2)->nullable();
            $table->boolean('budget_passed')->default(false); // 434.3, 434.4
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plt_feature_performance_budgets');
        Schema::dropIfExists('plt_efficiency_backlog_items');
    }
};
