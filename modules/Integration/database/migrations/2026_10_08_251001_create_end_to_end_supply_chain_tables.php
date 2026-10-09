<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supply_chain_control_maps', function (Blueprint $table) {
            $table->id();
            $table->string('batch_cycle_code')->unique();
            $table->decimal('otif_rate_pct', 5, 2);
            $table->decimal('days_of_supply', 6, 2);
            $table->decimal('cash_to_cash_days', 6, 2);
            $table->boolean('has_tradeoff_inconsistency')->default(false); // 251.5
            $table->text('tradeoff_review_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('supply_chain_control_tower_disruptions', function (Blueprint $table) {
            $table->id();
            $table->string('disruption_code')->unique();
            $table->string('origin_domain')->index();
            $table->string('severity'); // LOW, MEDIUM, HIGH, CRITICAL
            $table->integer('blast_radius_affected_nodes_count');
            $table->boolean('mitigation_plan_executed')->default(false);
            $table->text('mitigation_resolution_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('supply_chain_cost_to_serve_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_code')->unique();
            $table->decimal('manufacture_cost_usd', 15, 2);
            $table->decimal('move_transport_cost_usd', 15, 2);
            $table->decimal('sell_commercial_cost_usd', 15, 2);
            $table->decimal('service_support_cost_usd', 15, 2);
            $table->decimal('total_chain_cost_usd', 15, 2);
            $table->decimal('co2_emissions_kg', 10, 2); // 251.7 ESG
            $table->boolean('is_green_shipping_opted')->default(false); // 251.7
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supply_chain_cost_to_serve_orders');
        Schema::dropIfExists('supply_chain_control_tower_disruptions');
        Schema::dropIfExists('supply_chain_control_maps');
    }
};
