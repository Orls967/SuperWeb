<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_stress_simulations', function (Blueprint $table) {
            $table->id();
            $table->string('simulation_code')->unique();
            $table->integer('concurrent_requests_target')->default(10000); // 298.2
            $table->decimal('query_p95_latency_ms', 8, 2);
            $table->decimal('query_p99_latency_ms', 8, 2);
            $table->boolean('p99_budget_enforced')->default(true); // 298.2 (< 250ms)
            $table->boolean('stress_dataset_cleaned')->default(false); // 298.7
            $table->timestamps();
        });

        Schema::create('platform_security_permutations', function (Blueprint $table) {
            $table->id();
            $table->string('test_suite_code')->unique();
            $table->integer('total_permutations_tested');
            $table->integer('idor_fuzz_count');
            $table->integer('critical_findings_count')->default(0); // 298.3 Zero critical findings
            $table->integer('high_findings_count')->default(0); // 298.3 Zero high findings
            $table->boolean('is_security_cleared')->default(false);
            $table->timestamps();
        });

        Schema::create('platform_business_disaster_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->string('scenario_code')->unique(); // e.g. FESTIVAL_GRID_OUTAGE_HOSPITAL_SURGE
            $table->string('disaster_scenario_name');
            $table->decimal('total_money_reconciled_usd', 18, 2);
            $table->decimal('discrepancy_amount_usd', 18, 2)->default(0.00); // 298.4 Reconcile all money/stock/assets
            $table->boolean('all_assets_reconciled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_business_disaster_reconciliations');
        Schema::dropIfExists('platform_security_permutations');
        Schema::dropIfExists('platform_stress_simulations');
    }
};
