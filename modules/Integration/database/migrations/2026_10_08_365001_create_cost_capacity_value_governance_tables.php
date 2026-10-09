<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_cost_unit_economics_allocations', function (Blueprint $table) {
            $table->id();
            $table->string('allocation_code')->unique();
            $table->string('capability_code');
            $table->decimal('total_usage_units', 12, 2);
            $table->decimal('cost_per_unit_usd', 10, 4);
            $table->decimal('total_allocated_cost_usd', 12, 2);
            $table->boolean('usage_reconciled')->default(false); // 365.1 & 365.4
            $table->timestamps();
        });

        Schema::create('platform_service_readiness_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('review_code')->unique();
            $table->string('service_code')->index();
            $table->boolean('has_runbook')->default(false);
            $table->boolean('has_dashboard')->default(false);
            $table->boolean('has_rollback_plan')->default(false);
            $table->boolean('readiness_passed')->default(false); // 365.5 Edge case
            $table->boolean('release_held')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_service_readiness_reviews');
        Schema::dropIfExists('platform_cost_unit_economics_allocations');
    }
};
