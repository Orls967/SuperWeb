<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_utilization_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code')->unique();
            $table->string('asset_category'); // HAUL_TRUCK, CARGO_VESSEL, CT_SCANNER, CRANE
            $table->decimal('total_operating_hours', 10, 2);
            $table->decimal('idle_hours', 10, 2);
            $table->decimal('utilization_pct', 5, 2);
            $table->decimal('idle_cost_usd', 15, 2);
            $table->decimal('generated_revenue_usd', 15, 2);
            $table->decimal('net_contribution_usd', 15, 2); // 278.5
            $table->timestamps();
        });

        Schema::create('asset_allocations', function (Blueprint $table) {
            $table->id();
            $table->string('allocation_code')->unique();
            $table->string('asset_code')->index();
            $table->string('demand_contract_ref');
            $table->boolean('maintenance_constraint_cleared')->default(true); // 278.2
            $table->boolean('crew_certified_cleared')->default(true); // 278.2
            $table->string('status')->default('ALLOCATED'); // ALLOCATED, REJECTED_CONSTRAINT
            $table->timestamps();
        });

        Schema::create('asset_lifecycle_decisions', function (Blueprint $table) {
            $table->id();
            $table->string('decision_code')->unique();
            $table->string('asset_code')->index();
            $table->decimal('cumulative_repair_cost_usd', 15, 2);
            $table->decimal('estimated_replacement_cost_usd', 15, 2);
            $table->decimal('residual_salvage_value_usd', 15, 2);
            $table->string('recommended_action'); // REPAIR_AND_CONTINUE, REPLACE_NEW_ASSET
            $table->boolean('is_budget_encumbered')->default(false); // 278.6
            $table->boolean('is_capex_approved')->default(false); // 278.3 & 278.6
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_lifecycle_decisions');
        Schema::dropIfExists('asset_allocations');
        Schema::dropIfExists('asset_utilization_metrics');
    }
};
