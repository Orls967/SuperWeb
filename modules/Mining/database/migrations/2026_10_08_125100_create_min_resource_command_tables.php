<?php

declare(strict_types=1);

namespace Modules\Mining\database\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('min_resource_command_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('site_id');
            $table->string('snapshot_date');
            $table->double('pit_production_tonnage', 12, 3)->default(0.0);
            $table->double('plant_processing_tonnage', 12, 3)->default(0.0);
            $table->double('terminal_loaded_tonnage', 12, 3)->default(0.0);
            $table->double('live_commodity_price_usd', 12, 2)->default(0.0);
            $table->double('fleet_utilization_rate_pct', 5, 2)->default(0.0);
            $table->bigInteger('realized_revenue_minor')->default(0);
            $table->bigInteger('total_operating_cost_minor')->default(0);
            $table->bigInteger('net_mine_to_market_margin_minor')->default(0);
            $table->timestamps();

            $table->unique(['site_id', 'snapshot_date']);
        });

        Schema::create('min_life_of_mine_models', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('site_id');
            $table->string('model_code')->unique();
            $table->double('proven_reserves_tonnage', 14, 3);
            $table->double('annual_run_rate_tonnage', 14, 3);
            $table->double('life_of_mine_years', 6, 2);
            $table->bigInteger('projected_expansion_capex_minor')->default(0);
            $table->boolean('capex_dao_approved')->default(false);
            $table->timestamps();

            $table->index('site_id');
        });

        Schema::create('min_risk_heatmap_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('site_id');
            $table->string('risk_type'); // COMMODITY_PRICE_DROP, COVENANT_BREACH, ROYALTY_DEFAULT
            $table->double('threshold_pct', 5, 2);
            $table->double('actual_variance_pct', 5, 2);
            $table->string('severity')->default('HIGH'); // MEDIUM, HIGH, CRITICAL
            $table->text('remediation_action');
            $table->boolean('c_suite_notified')->default(true);
            $table->timestamp('triggered_at');
            $table->timestamps();

            $table->index(['site_id', 'risk_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('min_risk_heatmap_alerts');
        Schema::dropIfExists('min_life_of_mine_models');
        Schema::dropIfExists('min_resource_command_snapshots');
    }
};
