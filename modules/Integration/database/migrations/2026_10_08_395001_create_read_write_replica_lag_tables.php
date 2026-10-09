<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_stress_read_routing_queries', function (Blueprint $table) {
            $table->id();
            $table->string('query_code')->unique();
            $table->string('target_database'); // PRIMARY, REPLICA
            $table->boolean('is_financial_or_critical')->default(false); // 395.1 & 395.4
            $table->boolean('freshness_label_attached')->default(true); // 395.1 & 395.6 Risk
            $table->timestamps();
        });

        Schema::create('global_stress_replica_lag_fallbacks', function (Blueprint $table) {
            $table->id();
            $table->string('fallback_code')->unique();
            $table->integer('replica_lag_ms');
            $table->boolean('redirect_critical_to_primary')->default(true); // 395.2 & 395.5 Edge case
            $table->boolean('analytics_kept_on_replica')->default(true); // 395.5 Edge case
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_stress_replica_lag_fallbacks');
        Schema::dropIfExists('global_stress_read_routing_queries');
    }
};
