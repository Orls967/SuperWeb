<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sct_supplier_scorecards')) {
            Schema::create('sct_supplier_scorecards', function (Blueprint $table) {
                $table->id();
                $table->string('supplier_ref', 64);
                $table->string('supplier_name', 160);
                $table->string('period', 16);
                $table->unsignedInteger('composite_score')->default(0);
                $table->unsignedInteger('otd_score')->default(0);
                $table->unsignedInteger('reliability_score')->default(0);
                $table->unsignedInteger('volume_score')->default(0);
                $table->unsignedInteger('on_time_permil')->default(0);
                $table->unsignedInteger('mean_lead_time_days')->default(0);
                $table->unsignedInteger('sigma_lead_time_days')->default(0);
                $table->unsignedInteger('samples_count')->default(0);
                $table->string('tier', 24);
                $table->json('trend_json')->nullable();
                $table->text('action_recommendation')->nullable();
                $table->timestamp('computed_at')->nullable();
                $table->timestamps();

                $table->unique(['supplier_ref', 'period'], 'sct_sc_sup_period_uq');
                $table->index(['period', 'tier'], 'sct_sc_period_tier_idx');
            });
        }

        if (! Schema::hasTable('sct_inventory_health_snapshots')) {
            Schema::create('sct_inventory_health_snapshots', function (Blueprint $table) {
                $table->id();
                $table->string('item_code', 32);
                $table->string('echelon_node', 32);
                $table->string('health_status', 24);
                $table->string('aging_bucket', 16);
                $table->integer('on_hand_qty')->default(0);
                $table->integer('quarantine_qty')->default(0);
                $table->integer('available_qty')->default(0);
                $table->integer('daily_demand_qty')->default(0);
                $table->integer('dos_days')->default(0);
                $table->integer('oldest_lot_age_days')->default(0);
                $table->bigInteger('tied_capital_idr')->default(0);
                $table->bigInteger('obsolescence_risk_idr')->default(0);
                $table->date('snapshot_date');
                $table->timestamps();

                $table->unique(['item_code', 'echelon_node', 'snapshot_date'], 'sct_ih_item_node_date_uq');
                $table->index(['snapshot_date', 'health_status'], 'sct_ih_date_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sct_inventory_health_snapshots');
        Schema::dropIfExists('sct_supplier_scorecards');
    }
};
